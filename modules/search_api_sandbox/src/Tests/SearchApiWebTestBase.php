<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\SearchApiWebTestBase.
 */

namespace Drupal\search_api\Tests;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simpletest\WebTestBase;

/**
 * Provides the base class for web tests for Search API.
 */
abstract class SearchApiWebTestBase extends WebTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'search_api', 'search_api_test_backend');

  /**
   * Account object representing a user with Search API administration
   * privileges.
   *
   * @var \Drupal\Core\Session\AccountInterface $account
   */
  protected $adminUser;

  /**
   * Account object representing a user without Search API administration
   * privileges.
   *
   * @var \Drupal\Core\Session\AccountInterface $account
   */
  protected $unauthorizedUser;

  /**
   * Account object representing a anonymous user
   *
   * @var \Drupal\Core\Session\AccountInterface $account
   */
  protected $anonymousUser;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create users.
    $this->adminUser = $this->drupalCreateUser(array('administer search_api', 'access administration pages'));
    $this->unauthorizedUser = $this->drupalCreateUser(array('access administration pages'));
    $this->anonymousUser = $this->drupalCreateUser();

    $this->urlGenerator = $this->container->get('url_generator');

    // Create a node article type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create a node page type.
    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));
  }

  /**
   * Creates or deletes a server.
   *
   * @return \Drupal\search_api\Server\ServerInterface
   *   A search server.
   */
  public function getTestServer($name = 'WebTest server', $machine_name = 'webtest_server', $backend_id = 'search_api_test_backend', $options = array(), $reset = FALSE) {
    /** @var $server \Drupal\search_api\Server\ServerInterface */
    $server = entity_load('search_api_server', $machine_name);

    if ($server) {
      if ($reset) {
        $server->delete();
      }
    }
    else {
      $server = entity_create('search_api_server', array('name' => $name, 'machine_name' => $machine_name, 'backend' => $backend_id));
      $server->set('description', $name);
      $server->save();
    }

    return $server;
  }

  /**
   * Creates or deletes an index.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   A search server.
   */
  public function getTestIndex($name = 'WebTest Index', $machine_name = 'webtest_index', $server_id = 'webtest_server', $datasource_plugin_id = 'entity:node', $reset = FALSE) {
    /** @var $index \Drupal\search_api\Index\IndexInterface */
    $index = entity_load('search_api_index', $machine_name);

    if ($index) {
      if ($reset) {
        $index->delete();
      }
    }
    else {
      $index = entity_create('search_api_index', array('name' => $name, 'machine_name' => $machine_name, 'datasourcePluginId' => $datasource_plugin_id, 'server' => $server_id));
      $index->set('description', $name);
      $index->save();
    }

    return $index;
  }

}
