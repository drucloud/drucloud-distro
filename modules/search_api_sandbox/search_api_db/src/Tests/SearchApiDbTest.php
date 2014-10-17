<?php

/**
 * @file
 * Contains \Drupal\search_api_db\Tests\SearchApiDbTest.
 */

namespace Drupal\search_api_db\Tests;

use Drupal\Component\Utility\String;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Tests\ExampleContentTrait;
use Drupal\system\Tests\Entity\EntityUnitTestBase;
use SebastianBergmann\Exporter\Exception;

/**
 * Tests index and search capabilities using the Database search backend.
 *
 * @group search_api
 */
class SearchApiDbTest extends EntityUnitTestBase {

  use ExampleContentTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = array('field', 'menu_link', 'search_api', 'search_api_db', 'search_api_test_db');

  /**
   * A search server ID.
   *
   * @var string
   */
  protected $serverId = 'database_search_server';

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'database_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));
    $this->installSchema('system', array('router'));
    $this->installSchema('user', array('users_data'));

    $this->setUpExampleStructure();

    $this->installConfig(array('search_api_test_db'));
  }

  /**
   * Tests various indexing scenarios for the Database search backend.
   *
   * Uses a single method to save time.
   */
  public function testFramework() {
    $this->insertExampleContent();
    $this->checkDefaultServer();
    $this->checkServerTables();
    $this->checkDefaultIndex();
    $this->updateIndex();
    $this->searchNoResults();
    $this->indexItems($this->indexId);
    $this->searchSuccess1();
    $this->checkFacets();
    $this->regressionTests();
    $this->editServer();
    $this->searchSuccess2();
    $this->clearIndex();

    $this->enableHtmlFilter();
    $this->indexItems($this->indexId);
    $this->disableHtmlFilter();
    $this->clearIndex();

    $this->searchNoResults();
    $this->regressionTests2();
    $this->checkModuleUninstall();
  }

  /**
   * Tests the server that was installed through default configuration files.
   */
  protected function checkDefaultServer() {
    $server = Server::load($this->serverId);
    $this->assertTrue((bool) $server, 'The server was successfully created.');
  }

  /**
   * Tests that all tables and all columns have been created.
   */
  protected function checkServerTables() {
    /** @var \Drupal\search_api\Server\ServerInterface $server */
    $server = Server::load($this->serverId);

    $normalized_storage_table = $server->getBackendConfig()['index_tables'][$this->indexId];
    $field_tables = $server->getBackendConfig()['field_tables'][$this->indexId];

    $this->assertTrue(\Drupal::database()->schema()->tableExists($normalized_storage_table), 'Normalized storage table exists');
    foreach ($field_tables as $field_table) {
      $this->assertTrue(\Drupal::database()->schema()->tableExists($field_table['table']), String::format('Field table %table exists', array('%table' => $field_table['table'])));
      $this->assertTrue(\Drupal::database()->schema()->fieldExists($normalized_storage_table, $field_table['column']), String::format('Field column %column exists', array('%column' => $field_table['column'])));
    }
  }

  /**
   * Tests the index that was installed through default configuration files.
   */
  protected function checkDefaultIndex() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $this->assertTrue((bool) $index, 'The index was successfully created.');

    $this->assertEqual($index->getTracker()->getTotalItemsCount(), 5, 'Correct item count.');
    $this->assertEqual($index->getTracker()->getIndexedItemsCount(), 0, 'All items still need to be indexed.');
  }

  /**
   * Checks whether changes to the index's fields are picked up by the server.
   */
  protected function updateIndex() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);

    // Remove a field from the index and check if the change is matched in the
    // server configuration.
    $field_id = $this->getFieldId('keywords');
    if (empty($index->getFields()[$field_id])) {
      throw new Exception();
    }
    $index->getFields()[$field_id]->setIndexed(FALSE, TRUE);
    $index->save();

    /** @var \Drupal\search_api\Server\ServerInterface $server */
    $server = Server::load($this->serverId);
    $index_fields = array_keys($index->getOption('fields', array()));
    $server_fields = array_keys($server->getBackendConfig()['field_tables'][$index->id()]);
    sort($index_fields);
    sort($server_fields);
    $this->assertEqual($index_fields, $server_fields);

    // Add the field back for the next assertions.
    $index->getFields(FALSE)[$field_id]->setIndexed(TRUE, TRUE);
    $index->save();
  }

  /**
   * Enable the "HTML Filter" processor for the index.
   */
  protected function enableHtmlFilter() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);

    $index->getFields(FALSE)[$this->getFieldId('body')]->setIndexed(TRUE, TRUE);

    $processors = $index->getOption('processors', array());
    $processors['html_filter'] = array(
      'status' => TRUE,
      'weight' => 0,
    );
    $index->setOption('processors', $processors);
    $index->save();
  }

  /**
   * Disable the "HTML Filter" processor for the index.
   */
  protected function disableHtmlFilter() {
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $processors = $index->getOption('processors');
    $processors['html_filter'] = array(
      'status' => FALSE,
      'weight' => 0,
    );
    $index->setOption('processors', $processors);
    $index->getFields()[$this->getFieldId('body')]->setIndexed(FALSE, TRUE);
    $index->save();
  }

  /**
   * Builds a search query for testing purposes.
   *
   * Used as a helper method during testing.
   *
   * @param string|array|null $keys
   *   (optional) The search keys to set, if any.
   * @param array $filters
   *   (optional) Filters to set on the query, in the format "field,value".
   * @param array $fields
   *   (optional) Fulltext fields to search for the keys.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query on the test index.
   */
  protected function buildSearch($keys = NULL, array $filters = array(), array $fields = array()) {
    $query = Index::load($this->indexId)->query();
    if ($keys) {
      $query->keys($keys);
      if ($fields) {
        $query->fields($fields);
      }
    }
    foreach ($filters as $filter) {
      list($field, $value) = explode(',', $filter, 2);
      $query->condition($this->getFieldId($field), $value);
    }
    $query->range(0, 10);

    return $query;
  }

  /**
   * Tests that a search on the index doesn't have any results.
   */
  protected function searchNoResults() {
    $results = $this->buildSearch('test')->execute();
    $this->assertEqual($results->getResultCount(), 0, 'No search results returned without indexing.');
    $this->assertEqual(array_keys($results->getResultItems()), array(), 'No search results returned without indexing.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Tests whether some test searches have the correct results.
   */
  protected function searchSuccess1() {
    $results = $this->buildSearch('test')->range(1, 2)->sort($this->getFieldId('id'), 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 3)), 'Search for »test« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $ids = $this->getItemIds(array(2));
    $id = reset($ids);
    if ($this->assertEqual(key($results->getResultItems()), $id)) {
      $this->assertEqual($results->getResultItems()[$id]->getId(), $id);
      $this->assertEqual($results->getResultItems()[$id]->getDatasourceId(), 'entity:entity_test');
    }

    $results = $this->buildSearch('test foo')->sort($this->getFieldId('id'), 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Search for »test foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4)), 'Search for »test foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch('foo', array('type,item'))->sort($this->getFieldId('id'), 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2)), 'Search for »foo« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Complex search 1 returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(4)), 'Complex search 1 returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Tests whether facets work correctly.
   */
  protected function checkFacets() {
    // @todo Fix facets.
//    $query = $this->buildSearch();
//    $filter = $query->createFilter('OR', array('facet:type'));
//    $filter->condition($this->getFieldId('type'), 'article');
//    $query->filter($filter);
//    $facets['type'] = array(
//      'field' => $this->getFieldId('type'),
//      'limit' => 0,
//      'min_count' => 1,
//      'missing' => TRUE,
//      'operator' => 'or',
//    );
//    $query->setOption('search_api_facets', $facets);
//    $query->range(0, 0);
//    $results = $query->execute();
//    $this->assertEqual($results->getResultCount(), 2, 'OR facets query returned correct number of results.');
//    $expected = array(
//      array('count' => 2, 'filter' => '"article"'),
//      array('count' => 2, 'filter' => '"item"'),
//      array('count' => 1, 'filter' => '!'),
//    );
//    $this->assertEqual($expected, $results->getExtraData('search_api_facets')['type'], 'Correct OR facets were returned');
//
//    $query = $this->buildSearch();
//    $filter = $query->createFilter('OR', array('facet:' . $this->getFieldId('type')));
//    $filter->condition($this->getFieldId('type'), 'article');
//    $query->filter($filter);
//    $filter = $query->createFilter('AND');
//    $filter->condition($this->getFieldId('type'), NULL, '<>');
//    $query->filter($filter);
//    $facets['type'] = array(
//      'field' => $this->getFieldId('type'),
//      'limit' => 0,
//      'min_count' => 1,
//      'missing' => TRUE,
//      'operator' => 'or',
//    );
//    $query->setOption('search_api_facets', $facets);
//    $query->range(0, 0);
//    $results = $query->execute();
//    $this->assertEqual($results->getResultCount(), 2, 'OR facets query returned correct number of results.');
//    $expected = array(
//      array('count' => 2, 'filter' => '"article"'),
//      array('count' => 2, 'filter' => '"item"'),
//    );
//    $this->assertEqual($expected, $results->getExtraData('search_api_facets')['type'], 'Correct OR facets were returned');
  }

  /**
   * Edits the server to change the "Minimum word length" setting.
   */
  protected function editServer() {
    /** @var \Drupal\search_api\Server\ServerInterface $server */
    $server = Server::load($this->serverId);
    $backend_config = $server->getBackendConfig();
    $backend_config['min_chars'] = 4;
    $server->setBackendConfig($backend_config);
    $success = (bool) $server->save();
    $this->assertTrue($success, 'The server was successfully edited.');

    $this->clearIndex();
    $this->indexItems($this->indexId);

    // Reset the internal cache so the new values will be available.
    \Drupal::entityManager()->getStorage('search_api_index')->resetCache(array($this->indexId));
  }

  /**
   * Tests the results of some test searches with minimum word length of 4.
   */
  protected function searchSuccess2() {
    $results = $this->buildSearch('test')->range(1, 2)->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(4, 1)), 'Search for »test« returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch(NULL, array('body,test foobar'))->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Search with multi-term fulltext filter returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Search with multi-term fulltext filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch('test foo')->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Search for »test foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 1, 3)), 'Search for »test foo« returned correct result.');
    $this->assertIgnored($results, array('foo'), 'Short key was ignored.');
    $this->assertWarnings($results);

    $results = $this->buildSearch('foo', array('type,item'))->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Search for »foo« returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 3)), 'Search for »foo« returned correct result.');
    $this->assertIgnored($results, array('foo'), 'Short key was ignored.');
    $this->assertWarnings($results, array($this->t('No valid search keys were present in the query.')), 'No warnings were displayed.');

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Complex search 1 returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Complex search 1 returned correct result.');
    $this->assertIgnored($results, array('baz', 'bar'), 'Correct keys were ignored.');
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'OR',
        'baz',
        'foobar',
      ),
      array(
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        'bar',
        'fooblob',
      ),
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Complex search 2 returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Complex search 2 returned correct result.');
    $this->assertIgnored($results, array('baz', 'bar'), 'Correct keys were ignored.');
    $this->assertWarnings($results);

    $results = $this->buildSearch(NULL, array('keywords,orange'))->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Filter query 1 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 5)), 'Filter query 1 on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $filters = array(
      'keywords,orange',
      'keywords,apple',
    );
    $results = $this->buildSearch(NULL, $filters)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter query 2 on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2)), 'Filter query 2 on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $results = $this->buildSearch()->condition($this->getFieldId('keywords'), NULL)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Query with NULL filter returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Query with NULL filter returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);
  }

  /**
   * Executes regression tests for issues that were already fixed.
   */
  protected function regressionTests() {
    // Regression tests for #2007872.
    $results = $this->buildSearch('test')->sort($this->getFieldId('id'), 'ASC')->sort($this->getFieldId('type'), 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Sorting on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 3, 4)), 'Sorting on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('id'), 3);
    $filter->condition($this->getFieldId('type'), 'article');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'OR filter on field with NULLs returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4, 5)), 'OR filter on field with NULLs returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #1863672.
    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'OR filter on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'OR filter on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $query->filter($filter);
    $filter = $query->createFilter('OR');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $query->filter($filter);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Multiple OR filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 5)), 'Multiple OR filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch();
    $filter1 = $query->createFilter('OR');
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'orange');
    $filter->condition($this->getFieldId('keywords'), 'apple');
    $filter1->filter($filter);
    $filter = $query->createFilter('AND');
    $filter->condition($this->getFieldId('keywords'), 'strawberry');
    $filter->condition($this->getFieldId('keywords'), 'grape');
    $filter1->filter($filter);
    $query->filter($filter1);
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'Complex nested filters on multi-valued field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(2, 4, 5)), 'Complex nested filters on multi-valued field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2040543.
    // @todo Fix facets.
//    $query = $this->buildSearch();
//    $facets['type'] = array(
//      'field' => $this->getFieldId('type'),
//      'limit' => 0,
//      'min_count' => 1,
//      'missing' => TRUE,
//    );
//    $query->setOption('search_api_facets', $facets);
//    $query->range(0, 0);
//    $results = $query->execute();
//    $expected = array(
//      array('count' => 2, 'filter' => '"article"'),
//      array('count' => 2, 'filter' => '"item"'),
//      array('count' => 1, 'filter' => '!'),
//    );
//    $type_facets = $results->getExtraData('search_api_facets')['type'];
//    usort($type_facets, array($this, 'facetCompare'));
//    $this->assertEqual($type_facets, $expected, 'Correct facets were returned');
//
//    $query = $this->buildSearch();
//    $facets['type']['missing'] = FALSE;
//    $query->setOption('search_api_facets', $facets);
//    $query->range(0, 0);
//    $results = $query->execute();
//    $expected = array(
//      array('count' => 2, 'filter' => '"article"'),
//      array('count' => 2, 'filter' => '"item"'),
//    );
//    $type_facets = $results->getExtraData('search_api_facets')['type'];
//    usort($type_facets, array($this, 'facetCompare'));
//    $this->assertEqual($type_facets, $expected, 'Correct facets were returned');

    // Regression tests for #2111753.
    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 3, 'OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4)), 'OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->range(0, 0);
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 5, 'Multi-field OR keywords returned correct number of results.');
    $this->assertFalse($results->getResultItems(), 'Multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      'foo',
      'test',
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Nested OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'Nested OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      array(
        '#conjunction' => 'AND',
        'foo',
        'test',
      ),
      array(
        '#conjunction' => 'AND',
        'bar',
        'baz',
      ),
    );
    $query = $this->buildSearch($keys, array(), array($this->getFieldId('name'), $this->getFieldId('body')));
    $query->sort($this->getFieldId('id'), 'ASC');
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 4, 'Nested multi-field OR keywords returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'Nested multi-field OR keywords returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2127001.
    $keys = array(
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      'foo',
      'bar',
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Negated AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4)), 'Negated AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'OR',
      '#negation' => TRUE,
      'foo',
      'baz',
    );
    $results = $this->buildSearch($keys)->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Negated OR fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'Negated OR fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $keys = array(
      '#conjunction' => 'AND',
      'test',
      array(
        '#conjunction' => 'AND',
        '#negation' => TRUE,
        'foo',
        'bar',
      ),
    );
    $results = $this->buildSearch($keys)->sort('search_api_id', 'ASC')->execute();
    $this->assertEqual($results->getResultCount(), 2, 'Nested NOT AND fulltext search returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3, 4)), 'Nested NOT AND fulltext search returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression tests for #2136409
    // @todo Fix NULL and NOT NULL conditions.
//    $query = $this->buildSearch();
//    $query->condition($this->getFieldId('type'), NULL);
//    $query->sort($this->getFieldId('id'), 'ASC');
//    $results = $query->execute();
//    $this->assertEqual($results->getResultCount(), 1, 'NULL filter returned correct number of results.');
//    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(3)), 'NULL filter returned correct result.');
//
//    $query = $this->buildSearch();
//    $query->condition($this->getFieldId('type'), NULL, '<>');
//    $query->sort($this->getFieldId('id'), 'ASC');
//    $results = $query->execute();
//    $this->assertEqual($results->getResultCount(), 4, 'NOT NULL filter returned correct number of results.');
//    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(1, 2, 4, 5)), 'NOT NULL filter returned correct result.');

    // Regression tests for #1658964.
    $query = $this->buildSearch();
    $facets['type'] = array(
      'field' => $this->getFieldId('type'),
      'limit' => 0,
      'min_count' => 0,
      'missing' => TRUE,
    );
    $query->setOption('search_api_facets', $facets);
    $query->condition($this->getFieldId('type'), 'article');
    $query->range(0, 0);
    $results = $query->execute();
    $expected = array(
      array('count' => 2, 'filter' => '"article"'),
      array('count' => 0, 'filter' => '!'),
      array('count' => 0, 'filter' => '"item"'),
    );
    $facets = $results->getExtraData('search_api_facets', array())['type'];
    usort($facets, array($this, 'facetCompare'));
    $this->assertEqual($facets, $expected, 'Correct facets were returned');
  }

  /**
   * Compares two facet filters to determine their order.
   *
   * Used as a callback for usort() in regressionTests().
   *
   * Will first compare the counts, ranking facets with higher count first, and
   * then by filter value.
   *
   * @param array $a
   *   The first facet filter.
   * @param array $b
   *   The second facet filter.
   *
   * @return int
   *   -1 or 1 if the first filter should, respectively, come before or after
   *   the second; 0 if both facet filters are equal.
   */
  protected function facetCompare(array $a, array $b) {
    if ($a['count'] != $b['count']) {
      return $b['count'] - $a['count'];
    }
    return strcasecmp($a['filter'], $b['filter']);
  }

  /**
   * Clears the test index.
   */
  protected function clearIndex() {
    Index::load($this->indexId)->clear();
  }

  /**
   * Executes regression tests which are unpractical to run in between.
   */
  protected function regressionTests2() {
    // Create a "keywords" field on the test entity type.
    FieldStorageConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'type' => 'decimal',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ))->save();
    FieldConfig::create(array(
      'field_name' => 'prices',
      'entity_type' => 'entity_test',
      'bundle' => 'item',
      'label' => 'Prices',
    ))->save();

    // Regression test for #1916474.
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $index->resetCaches();
    $fields = $index->getFields(FALSE);
    $price_field = $fields[$this->getFieldId('prices')];
    $price_field->setType('decimal')->setIndexed(TRUE, TRUE);
    $success = $index->save();
    $this->assertTrue($success, 'The index field settings were successfully changed.');

    // Reset the static cache so the new values will be available.
    \Drupal::entityManager()->getStorage('search_api_server')->resetCache(array($this->serverId));
    \Drupal::entityManager()->getStorage('search_api_index')->resetCache(array($this->serverId));

    entity_create('entity_test', array(
      'id' => 6,
      'prices' => array('3.5', '3.25', '3.75', '3.5'),
      'type' => 'item',
    ))->save();

    $this->indexItems($this->indexId);

    $query = $this->buildSearch(NULL, array('prices,3.25'));
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    $query = $this->buildSearch(NULL, array('prices,3.5'));
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 1, 'Filter on decimal field returned correct number of results.');
    $this->assertEqual(array_keys($results->getResultItems()), $this->getItemIds(array(6)), 'Filter on decimal field returned correct result.');
    $this->assertIgnored($results);
    $this->assertWarnings($results);

    // Regression test for #2284199.
    entity_create('entity_test', array(
      'id' => 7,
      'type' => 'item',
    ))->save();

    $count = $this->indexItems($this->indexId);
    $this->assertEqual($count, 1, 'Indexing an item with an empty value for a non string field worked.');
  }

  /**
   * Tests whether removing the configuration again works as it should.
   */
  protected function checkModuleUninstall() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    /** @var \Drupal\search_api\Server\ServerInterface $server */
    $server = Server::load($this->serverId);
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $index = Index::load($this->indexId);
    $server->deleteAllIndexItems($index);
    $query = $this->buildSearch();
    $results = $query->execute();
    $this->assertEqual($results->getResultCount(), 0, 'Clearing the server worked correctly.');
    $table = 'search_api_db_' . $this->indexId;
    $this->assertTrue(db_table_exists($table), 'The index tables were left in place.');

    // Remove first the index and then the server.
    $index->setServer();
    $index->save();

    $server = Server::load($this->serverId);
    $this->assertEqual($server->getBackendConfig()['field_tables'], array(), 'The index was successfully removed from the server.');
    $this->assertFalse(db_table_exists($table), 'The index tables were deleted.');
    $server->delete();

    // Uninstall the module.
    \Drupal::moduleHandler()->uninstall(array('search_api_db'), FALSE);
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('search_api_db'), 'The Database Search module was successfully disabled.');
    $prefix = \Drupal::database()->prefixTables('{search_api_db_}') . '%';
    $this->assertEqual(\Drupal::database()->schema()->findTables($prefix), array(), 'The Database Search module was successfully uninstalled.');
  }

  /**
   * Asserts ignored fields from a set of search results.
   */
  protected function assertIgnored(ResultSetInterface $results, array $ignored = array(), $message = 'No keys were ignored.') {
    $this->assertEqual($results->getIgnoredSearchKeys(), $ignored, $message);
  }

  /**
   * Asserts warnings from a set of search results.
   */
  protected function assertWarnings(ResultSetInterface $results, array $warnings = array(), $message = 'No warnings were displayed.') {
    $this->assertEqual($results->getWarnings(), $warnings, $message);
  }

}
