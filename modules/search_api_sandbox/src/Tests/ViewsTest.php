<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\ViewsTest.
 */

namespace Drupal\search_api\Tests;

/**
 * Provides views tests for Search API.
 *
 * @group search_api
 */
class ViewsTest extends SearchApiWebTestBase {

  use ExampleContentTrait;

  /**
   * A search index ID.
   *
   * @var string
   */
  protected $indexId = 'database_search_index';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('search_api_test_views');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpExampleStructure();
  }

  /**
   * Tests a view with a fulltext search field.
   */
  public function testFulltextSearch() {
    $this->insertExampleContent();
    $this->assertEqual($this->indexItems($this->indexId), 5, '5 items were indexed.');

    $this->drupalGet('search-api-test-fulltext');
    // By default, it should show all entities.
    foreach ($this->entities as $entity) {
      $this->assertText($entity->label());
    }

    // Search for something.
    $this->drupalGet('search-api-test-fulltext', array('query' => array('search_api_fulltext' => 'foobar')));

    // Now it should only find two entities.
    foreach ($this->entities as $id => $entity) {
      if ($id == 3) {
        $this->assertText($entity->label());
      }
      else {
        $this->assertNoText($entity->label());
      }
    }
  }

}
