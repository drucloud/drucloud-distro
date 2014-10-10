<?php

/**
 * @file
 * Contains \Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationLatlngFormatter.
 */

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal;

/**
 * Plugin implementation of the 'geolocation_latlng' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_latlng",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Lat/Lng"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationLatlngFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items) {
    $element = array();

    foreach ($items as $delta => $item) {
      $element[$delta] = array(
        '#theme' => 'geolocation_formatter_geolocation_latlng',
        '#lat' => $item->lat,
        '#lng' => $item->lng,
      );
    }

    return $element;
  }

}
