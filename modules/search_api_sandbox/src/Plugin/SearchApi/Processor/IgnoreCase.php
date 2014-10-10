<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Ignorecase.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "ignorecase",
 *   label = @Translation("Ignore case"),
 *   description = @Translation("Makes searches case-insensitive on selected fields.")
 * )
 */
class IgnoreCase extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // We don't touch integers, NULL values or the like.
    if (is_string($value)) {
      $value = Unicode::strtolower($value);
    }
  }

}
