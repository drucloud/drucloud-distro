<?php

/**
 * @file
 * Contains \Drupal\action\Tests\Menu\LocalActionsWebTest.
 */

namespace Drupal\search_api\Tests;

use Drupal\system\Tests\Menu\LocalActionTest;

/**
 * Tests that local actions are available.
 *
 * @group search_api
 */
class LocalActionsWebTest extends LocalActionTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = array('search_api');

  /**
   * The administrator account to use for the tests.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  public function setUp() {
    parent::setUp();
    // Create users.
    $this->adminUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->drupalLogin($this->adminUser);
  }

  public function testLocalAction() {}

  /**
   * Tests local actions existence.
   *
   * no data provider here :( keeping this structure for later ActionIntegration unit test.
   */
  public function testLocalActions() {
    foreach ($this->getSearchAPIPageRoutes() as $routes) {
      foreach ($routes as $route) {
        $actions = array(
          '/admin/config/search/search-api/add-server' => 'Add server', // entity.search_api_server.add_form
          '/admin/config/search/search-api/add-index' => 'Add index', // entity.search_api_index.add_form
        );
        $this->drupalGet($route);
        $this->assertLocalAction($actions);
      }
    }
  }

  /**
   * Provides a list of routes to test.
   */
  public function getSearchAPIPageRoutes() {
    return array(
      array('/admin/config/search/search-api'), // search_api.overview
    );
  }
}
