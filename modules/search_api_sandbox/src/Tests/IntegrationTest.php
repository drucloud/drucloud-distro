<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\IntegrationTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Exception\SearchApiException;

/**
 * Tests the overall functionality of the Search API framework and UI.
 *
 * @group search_api
 */
class IntegrationTest extends SearchApiWebTestBase {

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId;

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId;

  /**
   * Tests various UI interactions between servers and indexes.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    // Test that the overview page exists and its permissions work.
    $this->drupalGet('admin/config');
    $this->assertText('Search API', 'Search API menu link is displayed.');

    $this->drupalGet('admin/config/search/search-api');
    $this->assertResponse(200, 'Admin user can access the overview page.');

    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('admin/config/search/search-api');
    $this->assertResponse(403, "User without permissions doesn't have access to the overview page.");

    // Login as an admin user for the rest of the tests.
    $this->drupalLogin($this->adminUser);

    $this->serverId = $this->createServer();
    $this->createIndex();
    $this->trackContent();

    $this->addFieldsToIndex();
    $this->addAdditionalFieldsToIndex();
    $this->removeFieldsFromIndex();

    $this->addFilter();
    $this->configureFilterFields();

    $this->setReadOnly();
    $this->disableEnableIndex();
    $this->changeIndexDatasource();
    $this->changeIndexServer();

  }

  protected function createServer() {
    $server_id = drupal_strtolower($this->randomMachineName());
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_server.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200, 'Server add page exists');

    $edit = array(
      'name' => '',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Server name'))));

    $edit = array(
      'name' => 'Search API test server',
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));

    $edit = array(
      'name' => 'Search API test server',
      'machine_name' => $server_id,
      'status' => 1,
      'description' => 'A server used for testing.',
      'backend' => 'search_api_test_backend',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The server was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/server/' . $server_id, array(), $this->t('Correct redirect to server page.'));
    return $server_id;
  }

  protected function createIndex() {
    $settings_path = $this->urlGenerator->generateFromRoute('entity.search_api_index.add_form', array(), array('absolute' => TRUE));

    $this->drupalGet($settings_path);
    $this->assertResponse(200);
    $edit = array(
      'status' => 1,
      'description' => 'An index used for testing.',
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Index name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Machine-readable name'))));
    $this->assertText($this->t('!name field is required.', array('!name' => $this->t('Data types'))));

    $this->indexId = 'test_index';

    $edit = array(
      'name' => 'Search API test index',
      'machine_name' => $this->indexId,
      'status' => 1,
      'description' => 'An index used for testing.',
      'server' => $this->serverId,
      'datasources[]' => array('entity:node'),
    );

    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->assertText($this->t('The index was successfully saved.'));
    $this->assertUrl('admin/config/search/search-api/index/' . $this->indexId, array(), $this->t('Correct redirect to index page.'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    if ($this->assertTrue($index, 'Index was correctly created.')) {
      $this->assertEqual($index->label(), $edit['name'], $this->t('Name correctly inserted.'));
      $this->assertEqual($index->id(), $edit['machine_name'], $this->t('Index machine name correctly inserted.'));
      $this->assertTrue($index->status(), $this->t('Index status correctly inserted.'));
      $this->assertEqual($index->getDescription(), $edit['description'], $this->t('Index machine name correctly inserted.'));
      $this->assertEqual($index->getServerId(), $edit['server'], $this->t('Index server machine name correctly inserted.'));
      $this->assertEqual($index->getDatasourceIds(), $edit['datasources[]'], $this->t('Index datasource id correctly inserted.'));
    }
    else {
      throw new SearchApiException();
    }
  }

  protected function addFieldsToIndex() {
    $settings_path = $this->getIndexPath($this->indexId) . '/fields';

    $this->drupalGet($settings_path);
    $this->assertResponse(200);

    $edit = array(
      'fields[entity:node|nid][indexed]' => 1,
      'fields[entity:node|title][indexed]' => 1,
      'fields[entity:node|title][type]' => 'text',
      'fields[entity:node|title][boost]' => '21.0',
      'fields[entity:node|body][indexed]' => 1,
    );

    $this->drupalPostForm($settings_path, $edit, $this->t('Save changes'));
    $this->assertText($this->t('The changes were successfully saved.'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields(FALSE);

    $this->assertEqual($fields['entity:node|nid']->isIndexed(), $edit['fields[entity:node|nid][indexed]'], $this->t('nid field is indexed.'));
    $this->assertEqual($fields['entity:node|title']->isIndexed(), $edit['fields[entity:node|title][indexed]'], $this->t('title field is indexed.'));
    $this->assertEqual($fields['entity:node|title']->getType(), $edit['fields[entity:node|title][type]'], $this->t('title field type is text.'));
    $this->assertEqual($fields['entity:node|title']->getBoost(), $edit['fields[entity:node|title][boost]'], $this->t('title field boost value is 21.'));

    // Check that a 'parent_data_type.data_type' Search API field type => data
    // type mapping relationship works.
    $this->assertEqual($fields['entity:node|body']->getType(), 'text', 'Complex field mapping relationship works.');
  }

  protected function addAdditionalFieldsToIndex() {
    // Test that an entity reference field which targets a content entity is
    // shown.
    $this->assertFieldByName('additional[field][entity:node|uid]', NULL, 'Additional entity reference field targeting a content entity type is displayed.');

    // Test that an entity reference field which targets a config entity is not
    // shown as an additional field option.
    $this->assertNoFieldByName('additional[field][entity:node|type]', NULL,'Additional entity reference field targeting a config entity type is not displayed.');

    // @todo Implement more tests for additional fields.
  }

  protected function removeFieldsFromIndex() {
    $edit = array(
      'fields[entity:node|body][indexed]' => FALSE,
    );
    $this->drupalPostForm($this->getIndexPath($this->indexId) . '/fields', $edit, $this->t('Save changes'));

    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $fields = $index->getFields();
    $this->assertTrue(!isset($fields['entity:node|body']), 'The body field has been removed from the index.');
  }

  protected function trackContent() {
    // Initially there should be no tracked items, because there are no nodes
    $tracked_items = $this->countTrackedItems();

    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked yet'));

    // Add two articles and a page
    $article1 = $this->drupalCreateNode(array('type' => 'article'));
    $article2 = $this->drupalCreateNode(array('type' => 'article'));
    $page1 = $this->drupalCreateNode(array('type' => 'page'));

    // Those 3 new nodes should be added to the index immediately
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, $this->t('Three items are tracked'));

    // Create the edit index path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';

    // Test disabling the index
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked'));

    // Test enabling the index
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 3, $this->t('Three items are tracked'));

    // Test putting default to zero and no bundles checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all the items should get deleted.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('No items are tracked'));

    // Test putting default to zero and the article bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);

    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $this->drupalGet($settings_path);

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, $this->t('Two items are tracked'));

    // Test putting default to zero and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));

    // Test putting default to one and the article bundle checked.
    // This will add all bundles except the ones that are checked.
    // This means all pages should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));

    // Test putting default to one and the page bundle checked.
    // This will ignore all bundles except the ones that are checked.
    // This means all articles should be added.
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 1,
      'datasource_configs[entity:node][bundles][article]' => FALSE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 2, $this->t('Two items are tracked'));

    // Now lets delete an article. That should remove one item from the item
    // table
    $article1->delete();

    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 1, $this->t('One item is tracked'));
  }

  /**
   * Counts the number of tracked items from an index.
   *
   * @return int
   */
  protected function countTrackedItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getTotalItemsCount();
  }

  /**
   * Counts the number of remaining items from an index.
   *
   * @return int
   */
  protected function countRemainingItems() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId);
    return $index->getTracker()->getRemainingItemsCount();
  }

  /**
   * Sets an index to read only and checks if it reacts accordingly.
   *
   * The expected behavior is such that when an index is set to Read Only it
   * keeps tracking but when it comes to indexing it does not proceed to send
   * items to the server.
   */
  protected function setReadOnly() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    $edit = array(
      'status' => TRUE,
      'read_only' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    // This should have 2 items in the index

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $remaining_before = $this->countRemainingItems();

    $index_path = 'admin/config/search/search-api/index/' . $this->indexId;
    $this->drupalGet($index_path);

    $this->assertNoText($this->t('Index now'), $this->t("Making sure that the Index now button does not appear in the UI after setting the index to read_only"));

    // Let's index using the API also to make sure we can't index
    $index->index();

    $remaining_after = $this->countRemainingItems();
    $this->assertEqual($remaining_before, $remaining_after, $this->t('No items were indexed after setting to read_only'));

    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);

    $edit = array(
      'read_only' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $remaining_before = $index->getTracker()->getRemainingItemsCount();

    $this->drupalGet($index_path);
    $this->drupalPostForm(NULL, array(), $this->t('Index now'));

    $remaining_after = $index->getTracker()->getRemainingItemsCount();
    $this->assertNotEqual($remaining_before, $remaining_after, $this->t('Items were indexed after removing the read_only flag'));

  }

  /**
   * Disables and enables an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that when an index is disabled, all the items
   * from this index in the tracker are removed and it also tells the backend
   * to remove all items from this index.
   *
   * When it is enabled again, the items are re-added to the tracker.
   *
   */
  protected function disableEnableIndex() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // Disable the index
    $edit = array(
      'status' => FALSE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $tracked_items = $this->countTrackedItems();
    $this->assertEqual($tracked_items, 0, $this->t('After disabling the index, no items should be tracked'));

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // Enable the index
    $edit = array(
      'status' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $this->assertNotNull($tracked_items, $this->t('After enabling the index, at least 1 item should be tracked'));
  }

  /**
   * Changes datasources from an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that, when an index changes the
   * datasource configurations, the tracker should remove all items from the
   * datasources it no longer needs to handle and add the new ones.
   */
  protected function changeIndexDatasource() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $index->reindex();

    $tracked_items = $this->countTrackedItems();
    $user_count = \Drupal::entityQuery('user')->count()->execute();
    $node_count = \Drupal::entityQuery('node')->count()->execute();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // enable indexing of users
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
      'datasources[]' => array('entity:user', 'entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $t_args = array(
      '!usercount' => $user_count,
      '!nodecount' => $node_count,
    );
    $this->assertEqual($tracked_items, $user_count+$node_count, $this->t('After enabling user and nodes with respectively !usercount users and !nodecount nodes we should have the sum of those to index', $t_args));

    $this->drupalGet($settings_path);
    // Disable indexing of users
    $edit = array(
      'datasources[]' => array('entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $tracked_items = $this->countTrackedItems();
    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($tracked_items, $node_count, $this->t('After disabling user indexing we should only have !nodecount nodes to index', $t_args));
  }

  /**
   * Changes the server for an index and checks if it reacts accordingly.
   *
   * The expected behavior is such that, when an index changes the
   * server configurations, the tracker should remove all items from the
   * server it no longer is attached to and add the new ones.
   */
  protected function changeIndexServer() {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $this->indexId, TRUE);

    $node_count = \Drupal::entityQuery('node')->count()->execute();

    // Go to the index edit path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/edit';
    $this->drupalGet($settings_path);
    // enable indexing of nodes
    $edit = array(
      'status' => TRUE,
      'datasource_configs[entity:node][default]' => 0,
      'datasource_configs[entity:node][bundles][article]' => TRUE,
      'datasource_configs[entity:node][bundles][page]' => TRUE,
      'datasources[]' => array('entity:node'),
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    // Reindex all so we start from scratch
    $index->reindex();
    // We should have as many nodes as the node count
    $remaining_items = $this->countRemainingItems();

    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($remaining_items, $node_count, $this->t('We should have !nodecount nodes to index', $t_args));
    // Index
    $index->index();

    $remaining_items = $this->countRemainingItems();

    $this->assertEqual($remaining_items, 0, $this->t('We should have nothing left to index', $t_args));

    // Create a second server
    $serverId2 = $this->createServer();

    // Go to the index edit path
    $this->drupalGet($settings_path);
    // Change servers in the UI
    $edit = array(
      'server' => $serverId2,
    );
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));

    $remaining_items = $this->countRemainingItems();
    // After saving the new index, we should have called reindex.
    $t_args = array(
      '!nodecount' => $node_count,
    );
    $this->assertEqual($remaining_items, $node_count, $this->t('We should have !nodecount items left to index after changing servers', $t_args));
  }

  /**
   * Test that a filter can be added.
   */
  protected function addFilter() {
    // Go to the index filter path
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors[ignorecase][status]' => 1,
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    $this->assertTrue(isset($processors['ignorecase']), 'Ignore case processor enabled');
  }

  /**
   * Test that the filter can have fields configured.
   */
  protected function configureFilterFields() {
    $settings_path = 'admin/config/search/search-api/index/' . $this->indexId . '/filters';

    $edit = array(
      'processors[ignorecase][status]' => 1,
      'processors[ignorecase][settings][fields][search_api_language]' => FALSE,
      'processors[ignorecase][settings][fields][entity:node|title]' => 'entity:node|title',
    );
    $this->drupalPostForm($settings_path, $edit, $this->t('Save'));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = entity_load('search_api_index', $this->indexId, TRUE);
    $processors = $index->getProcessors();
    if (isset($processors['ignorecase'])) {
      $configuration = $processors['ignorecase']->getConfiguration();
      $this->assertTrue(empty($configuration['fields']['search_api_language']), 'Language field disabled for ignore case filter.');
    }
    else {
      $this->fail('"Ignore case" processor not enabled.');
    }
  }

  /**
   * Returns the system path for an index.
   *
   * @param string $index_id
   *   The index ID.
   *
   * @return string
   *   A system path.
   */
  protected function getIndexPath($index_id) {
    return 'admin/config/search/search-api/index/' . $index_id;
  }

}
