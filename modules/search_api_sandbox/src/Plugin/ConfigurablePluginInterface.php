<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\ConfigurablePluginInterface.
 */

namespace Drupal\search_api\Plugin;

use Drupal\Component\Plugin\ConfigurablePluginInterface as DrupalConfigurablePluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Describes a configurable Search API plugin.
 */
interface ConfigurablePluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface, DrupalConfigurablePluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the label for use on the administration pages.
   *
   * @return string
   *   The administration label.
   */
  public function label();

  /**
   * Returns the summary of the plugin configuration.
   *
   * @return string
   *   The configuration summary.
   */
  // @todo Clarify whether this needs to be sanitized. And rename.
  public function summary();

}
