<?php

/**
 * @file
 * Contains Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem.
 */

namespace Drupal\geolocation\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'geolocation' field type.
 *
 * @FieldType(
 *   id = "geolocation",
 *   label = @Translation("Geolocation"),
 *   description = @Translation("This field stores location data (lat, lng)."),
 *   default_widget = "geolocation_latlng",
 *   default_formatter = "geolocation_latlng"
 * )
 */
class GeolocationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'lat' => array(
          'description' => 'Stores the latitude value',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ),
        'lng' => array(
          'description' => 'Stores the longitude value',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ),
        'lat_sin' => array(
          'description' => 'Stores the sine of latitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ),
        'lat_cos' => array(
          'description' => 'Stores the cosine of latitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ),
        'lng_rad' => array(
          'description' => 'Stores the radian longitude',
          'type' => 'float',
          'size' => 'big',
          'not null' => TRUE,
        ),
      ),
      'indexes' => array(
        'lat' => array('lat'),
        'lng' => array('lng'),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['lat'] = DataDefinition::create('float')
      ->setLabel(t('Latitude'));

    $properties['lng'] = DataDefinition::create('float')
      ->setLabel(t('Longitude'));

    /*
    $properties['lat_sin'] = DataDefinition::create('float')
      ->setLabel(t('Calculated lat_sin'));

    $properties['lat_cos'] = DataDefinition::create('float')
      ->setLabel(t('Calculated lat_cos'));

    $properties['lng_rad'] = DataDefinition::create('float')
      ->setLabel(t('Calculated lng_rad'));
    */

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $lat = $this->get('lat')->getValue();
    $lng = $this->get('lng')->getValue();
    return $lat === NULL || $lat === '' || $lng === NULL || $lng === '';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $this->lat = trim($this->lat);
    $this->lng = trim($this->lng);
    $this->lat_sin = sin(deg2rad($this->lat));
    $this->lat_cos = cos(deg2rad($this->lat));
    $this->lng_rad = deg2rad($this->lng);
  }

}
