<?php

/**
 * @file
 * Contains \Drupal\geolocation\GeolocationFieldTest.
 */

namespace Drupal\geolocation\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the creation of geolocation fields.
 *
 * @group geolocation
 */
class GeolocationFieldTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'field',
    'node',
    'geolocation'
  );

  protected $field;
  protected $web_user;

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article'));
    $this->article_creator = $this->drupalCreateUser(array('create article content', 'edit own article content'));
    $this->drupalLogin($this->article_creator);
  }

  // Test fields.

  /**
   * Helper function for testGeolocationField().
   */
  function testGeolocationField() {

    // Add the geolocation field to the article content type.
    entity_create('field_storage_config', array(
      'field_name' => 'field_geolocation',
      'entity_type' => 'node',
      'type' => 'geolocation',
    ))->save();
    entity_create('field_config', array(
      'field_name' => 'field_geolocation',
      'label' => 'Geolocation',
      'entity_type' => 'node',
      'bundle' => 'article',
    ))->save();

    entity_get_form_display('node', 'article', 'default')
      ->setComponent('field_geolocation', array(
        'type' => 'geolocation_latlng',
      ))
      ->save();

    entity_get_display('node', 'article', 'default')
      ->setComponent('field_geolocation', array(
        'type' => 'geolocation_latlng',
        'weight' => 1,
      ))
      ->save();

    // Display creation form.
    $this->drupalGet('node/add/article');
    $this->assertFieldByName("field_geolocation[0][lat]", '', 'Geolocation lat input field found.');
    $this->assertFieldByName("field_geolocation[0][lng]", '', 'Geolocation lng input field found.');

    // Test basic entery of geolocation field.
    $lat = '49.880657';
    $lng = '10.869212';
    $edit = array(
      'title[0][value]' => $this->randomMachineName(),
      'field_geolocation[0][lat]' => $lat,
      'field_geolocation[0][lng]' => $lng,
    );

    $this->drupalPostForm(NULL, $edit, t('Save'));
    $expected =  '<div itemprop="location">';
    $expected .= '<span itemscope itemtype="http://schema.org/Place">';
    $expected .= '<div itemprop="geo">';
    $expected .= '<span itemscope itemtype="http://schema.org/GeoCoordinates">';
    $expected .= '<span property="latitude" content="' . $lat . '">' . $lat . '</span>,<span property="longitude" content="' . $lng . '">' . $lng . '</span>';
    $expected .= '</span>';
    $expected .= '</div>';
    $expected .= '</span>';
    $expected .= '</div>';
    $this->assertRaw($expected, 'Default microdata theme implementation for a geolocation with latitude, longitude was found on the article node page.');
  }
}
