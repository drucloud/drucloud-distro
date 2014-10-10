<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api\Menu\LocalTasksTest.
 */

namespace Drupal\Tests\search_api\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests whether Search API's local tasks work correctly.
 *
 * @group search_api
 */
class LocalTasksTest extends LocalTaskIntegrationTest {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->directoryList = array('search_api' => 'modules/search_api');
  }

  /**
   * Tests local task existence.
   *
   * @dataProvider getPageRoutesServer
   */
  public function testLocalTasksServer($route) {
    $tasks = array(
      0 => array('entity.search_api_server.canonical', 'entity.search_api_server.edit_form'),
    );
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Tests local task existence.
   *
   * @dataProvider getPageRoutesIndex
   */
  public function testLocalTasksIndex($route) {
    $tasks = array(
      0 => array('entity.search_api_index.canonical', 'entity.search_api_index.edit_form', 'entity.search_api_index.fields', 'entity.search_api_index.filters'),
    );
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provides a list of routes to test.
   */
  public function getPageRoutesServer() {
    return array(
      array('entity.search_api_server.canonical'),
      array('entity.search_api_server.edit_form'),
    );
  }

  /**
   * Provides a list of routes to test.
   */
  public function getPageRoutesIndex() {
    return array(
      array('entity.search_api_index.canonical'),
      array('entity.search_api_index.edit_form'),
      array('entity.search_api_index.filters'),
    );
  }

}
