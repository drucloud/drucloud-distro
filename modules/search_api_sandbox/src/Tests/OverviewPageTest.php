<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\OverviewPageTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Server\ServerInterface;

/**
 * Tests the Search API overview page.
 *
 * @group search_api
 */
class OverviewPageTest extends SearchApiWebTestBase {

  /**
   * The path of the overview page.
   *
   * @var string
   */
  protected $overviewPageUrl;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $this->overviewPageUrl = 'admin/config/search/search-api';
  }

  /**
   * Tests the creation of a server and an index.
   */
  public function testServerAndIndexCreation() {
    // Test the creation of a server.
    $server = $this->getTestServer();

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($server->label(), 'Server present on overview page.');
    $this->assertRaw($server->get('description'), 'Description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '") and contains(@class, "search-api-list-enabled")]', NULL, 'Server is in proper table');

    // Test the creation of an index.
    $index = $this->getTestIndex();

    $this->drupalGet($this->overviewPageUrl);

    $this->assertText($index->label(), 'Index present on overview page.');
    $this->assertRaw($index->get('description'), 'Index description is present');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '") and contains(@class, "search-api-list-enabled")]', NULL, 'Index is in proper table');

    // Test that an entity without bundles can be used as data source.
    $edit = array(
      'name' => $this->randomMachineName(),
      'machine_name' => Unicode::strtolower($this->randomMachineName()),
      'datasources[]' => 'entity:user',
    );
    $this->drupalPostForm('admin/config/search/search-api/add-index', $edit, $this->t('Save'));
    $this->assertText($this->t('The index was successfully saved.'));
    $this->assertText($edit['name']);
  }

  /**
   * Tests enable/disable operations for servers and indexes through the UI.
   */
  public function testServerAndIndexStatusChanges() {
    $server = $this->getTestServer();
    $this->assertEntityStatusChange($server);

    $index = $this->getTestIndex();
    $this->assertEntityStatusChange($index);

    // Disable the server and test that both itself and the index has been
    // disabled.
    $server->setStatus(FALSE)->save();
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $server->getEntityTypeId() . '-' . $server->id() . '") and contains(@class, "search-api-list-disabled")]', NULL, 'The server has been disabled.');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '") and contains(@class, "search-api-list-disabled")]', NULL, 'The index has been disabled.');

    // Test that an index can't be enabled if its server is disabled.
    $this->clickLink('Enable', 1);
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '") and contains(@class, "search-api-list-disabled")]', NULL, 'The index could not be enabled.');

    // Enable the server and try again.
    $server->setStatus(TRUE)->save();
    $this->drupalGet($this->overviewPageUrl);

    // This time the server is enabled so the first 'enable' link belongs to the
    // index.
    $this->clickLink('Enable');
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $index->getEntityTypeId() . '-' . $index->id() . '") and contains(@class, "search-api-list-enabled")]', NULL, 'The index has been enabled.');

    // Create a new index without a server assigned and test that it can't be
    // enabled. The overview UI is not very consistent at the moment, so test
    // using API functions for now.
    $index2 = $this->getTestIndex('WebTest Index 2', 'webtest_index_2', NULL);
    $this->assertFalse($index2->status(), 'The newly created index without a server is disabled by default.');

    $index2->setStatus(TRUE)->save();
    $this->assertFalse($index2->status(), 'The newly created index without a server cannot be enabled.');
  }

  /**
   * Asserts enable/disable operations for a search server or index.
   *
   * @param \Drupal\search_api\Server\ServerInterface|\Drupal\search_api\Index\IndexInterface $entity
   *   A search server or index.
   */
  protected function assertEntityStatusChange($entity) {
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $entity->getEntityTypeId() . '-' . $entity->id() . '") and contains(@class, "search-api-list-enabled")]', NULL, 'The newly created entity is enabled by default.');

    // The first 'disable' link on the page belongs to our newly created server
    // and the second 'disable' link belongs to our newly created index.
    if ($entity instanceof ServerInterface) {
      $this->clickLink('Disable');
    }
    else {
      $this->clickLink('Disable', 1);
    }

    // Submit the confirmation form and test that entity has been disabled.
    $this->drupalPostForm(NULL, array(), 'Disable');
    $this->assertFieldByXPath('//tr[contains(@class,"' . $entity->getEntityTypeId() . '-' . $entity->id() . '") and contains(@class, "search-api-list-disabled")]', NULL, 'The entity has been disabled.');

    // Now enable the entity.
    $this->clickLink('Enable');

    // And test that the enable operation succeeded.
    $this->drupalGet($this->overviewPageUrl);
    $this->assertFieldByXPath('//tr[contains(@class,"' . $entity->getEntityTypeId() . '-' . $entity->id() . '") and contains(@class, "search-api-list-enabled")]', NULL, 'The entity has benn enabled.');
  }

  /**
   * Tests server operations in the overview page.
   */
  public function testOperations() {
    /** @var $server \Drupal\search_api\Server\ServerInterface */
    $server = $this->getTestServer();

    $this->drupalGet($this->overviewPageUrl);
    $basic_url = $this->urlGenerator->generateFromRoute('entity.search_api_server.canonical', array('search_api_server' => $server->id()));
    $this->assertRaw('<a href="' . $basic_url . '/edit">Edit</a>', 'Edit operation presents');
    $this->assertRaw('<a href="' . $basic_url . '/disable">Disable</a>', 'Disable operation presents');
    $this->assertRaw('<a href="' . $basic_url . '/delete">Delete</a>', 'Delete operation presents');
    $this->assertNoRaw('<a href="' . $basic_url . '/enable">Enable</a>', 'Enable operation is not present');

    $server->setStatus(FALSE)->save();
    $this->drupalGet($this->overviewPageUrl);

    // As CsrfTokenGenerator uses current session Id we can not generate valid token
    $this->assertRaw('<a href="' . $basic_url .'/enable?token=', 'Enable operation present');
    $this->assertNoRaw('<a href="' . $basic_url .'/disable">Disable</a>', 'Disable operation  is not present');
  }

}
