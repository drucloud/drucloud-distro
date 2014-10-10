<?php

/**
 * @file
 * Contains \Drupal\field\Tests\GeolocationItemTest.
 */

namespace Drupal\geolocation\Tests;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\field\Tests\FieldUnitTestBase;

/**
 * Tests the new entity API for the geolocation field type.
 *
 * @group geolocation
 */
class GeolocationItemTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('geolocation');

  protected function setUp() {
    parent::setUp();

    // Create a geolocation field storage and field for validation.
    entity_create('field_storage_config', array(
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'geolocation',
    ))->save();
    entity_create('field_config', array(
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
    ))->save();
  }

  /**
   * Tests using entity fields of the geolocation field type.
   */
  public function testTestItem() {
    // Verify entity creation.
    $entity = entity_create('entity_test');
    $entity->name->value = $this->randomMachineName();
    $lat = '49.880657';
    $lng = '10.869212';
    $entity->field_test->lat = $lat;
    $entity->field_test->lng = $lng;
    $entity->save();

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = entity_load('entity_test', $id);
    $this->assertTrue($entity->field_test instanceof FieldItemListInterface, 'Field implements interface.');
    $this->assertTrue($entity->field_test[0] instanceof FieldItemInterface, 'Field item implements interface.');
    $this->assertEqual($entity->field_test->lat, $lat, "Lat {$entity->field_test->lat} is equal to lat {$lat}.");
    $this->assertEqual($entity->field_test[0]->lat, $lat, "Lat {$entity->field_test[0]->lat} is equal to lat {$lat}.");
    $this->assertEqual($entity->field_test->lng, $lng, "Lng {$entity->field_test[0]->lng} is equal to lng {$lng}.");
    $this->assertEqual($entity->field_test[0]->lng, $lng, "Lng {$entity->field_test[0]->lng} is equal to lng {$lng}.");

    // Verify changing the field value.
    $new_lat = rand(-90, 90) - rand(0, 999999)/1000000;
    $new_lng = rand(-180, 180) - rand(0, 999999)/1000000;
    $entity->field_test->lat = $new_lat;
    $entity->field_test->lng = $new_lng;
    $this->assertEqual($entity->field_test->lat, $new_lat, "Lat {$entity->field_test->lat} is equal to new lat {$new_lat}.");
    $this->assertEqual($entity->field_test->lng, $new_lng, "Lng {$entity->field_test->lng} is equal to new lng {$new_lng}.");

    // Read changed entity and assert changed values.
    $entity->save();
    $entity = entity_load('entity_test', $id);
    $this->assertEqual($entity->field_test->lat, $new_lat, "Lat {$entity->field_test->lat} is equal to new lat {$new_lat}.");
    $this->assertEqual($entity->field_test->lng, $new_lng, "Lng {$entity->field_test->lng} is equal to new lng {$new_lng}.");
  }

}
