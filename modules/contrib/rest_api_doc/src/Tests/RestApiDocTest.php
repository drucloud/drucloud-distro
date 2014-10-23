<?php

/**
 * @file
 * Contains \Drupal\rest_api_doc\Tests\RestApiDocTest.
 */

namespace Drupal\rest_api_doc\Tests;

use Drupal\rest\Tests\RESTTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests self document REST API functionality.
 *
 * @group rest_api_doc
 */
class RestApiDocTest extends RESTTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'hal',
    'rest',
    'node',
    'entity_test',
    'rest_test_views',
    'rest_api_doc',
  );

  /**
   * Test views to setup.
   *
   * @var array
   */
  public static $testViews = array('test_serializer_display_entity');

  /**
   * Tests module functionality.
   */
  public function testRestApiDoc() {
    // Enable REST API for node and entity_test entity types.
    $config = \Drupal::config('rest.settings');
    $settings = array();
    $resources = array(
      'node' => array('GET', 'POST', 'DELETE'),
      'entity_test' => array('DELETE'),
    );
    foreach ($resources as $entity_type => $methods) {
      foreach ($methods as $method) {
        $settings['entity:' . $entity_type][$method]['supported_formats'][] = $this->defaultFormat;
        $settings['entity:' . $entity_type][$method]['supported_auth'] = $this->defaultAuth;
      }
    }
    $config->set('resources', $settings);
    $config->save();
    $this->rebuildCache();

    $this->drupalCreateContentType(array('type' => 'stampy'));
    ViewTestData::createTestViews(get_class($this), array('rest_test_views'));
    $permissions = $this->entityPermissions('node', 'view');
    $permissions[] = 'restful get entity:node';
    $permissions = $this->entityPermissions('entity_test', 'view');
    $permissions[] = 'restful get entity:entity_test';
    $permissions[] = 'access content';
    $permissions[] = 'access rest_api_doc';
    $permissions[] = 'administer rest_api_doc';
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    $this->drupalGet('admin/config/services/rest-api-doc');
    $this->assertResponse(200);
    // We should see these rest-routes.
    $fields = array(
      'rest.entity.entity_test.DELETE',
      'rest.entity.node.GET.hal_json',
      'rest.entity.node.POST',
      'rest.entity.node.DELETE',
      'rest.csrftoken',
      'view.test_serializer_display_entity.rest_export_1',
    );
    $edit = array();
    foreach ($fields as $field) {
      $this->assertFieldByName("routes[$field]");
      $edit["routes[$field]"] = 1;
    }
    $text = "Some of them act badly because they've had a hard life,
      or have been mistreated...but, like people, some of them are just jerks -
      [site:name]";
    $edit['overview'] = $text;
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));
    $this->drupalGet('api/doc');
    $this->assertResponse(200);
    $this->assertText(\Drupal::token()->replace($text));
    $this->assertLink('/entity/node');
    $this->assertLink('/node/{node}');
    $this->assertLink('/entity_test/{entity_test}');
    $this->assertLink('/test/serialize/entity');
    $this->assertLink('/rest/session/token');
    $this->clickLink('/entity/node');
    $this->assertText('Authentication methods');
    $this->assertText('CSRF token: REQUIRED');
    $this->assertText('Required permissions');
    $this->assertText('Access POST on Content resource');
    $this->assertText('Requirements');
    $this->assertText('Parameters');
    $this->assertText('A boolean indicating whether the node is published');
    $this->assertText('Fields available for stampy (type = stampy)');
    $this->assertText('body');

    // Check the node/{node} path.
    $this->drupalGet('api/doc/::node::{node}');
    $this->assertText('cookie');
    $this->assertText('Access DELETE on Content resource');
    $this->assertText('Supported formats');
    $this->assertText('hal_json');
  }
}
