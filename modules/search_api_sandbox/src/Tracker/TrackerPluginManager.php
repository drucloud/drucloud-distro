<?php

/**
 * @file
 * Contains \Drupal\search_api\Tracker\TrackerPluginManager.
 */

namespace Drupal\search_api\Tracker;

use Drupal\Component\Utility\String;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Search API tracker plugin manager.
 */
class TrackerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a TrackerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    // Initialize the parent chain of objects.
    parent::__construct('Plugin/SearchApi/Tracker', $namespaces, $module_handler, 'Drupal\search_api\Tracker\TrackerInterface', 'Drupal\search_api\Annotation\SearchApiTracker');
    // Configure the plugin manager.
    $this->setCacheBackend($cache_backend, 'search_api_trackers');
    $this->alterInfo('search_api_tracker_info');
  }

  /**
   * Gets a list of plugin definition labels.
   *
   * @return array
   *   An associative array containing the plugin label, keyed by the plugin ID.
   */
  public function getDefinitionLabels() {
    // Initialize the options variable to an empty array.
    $options = array();
    // Iterate through the tracker plugin definitions.
    foreach ($this->getDefinitions() as $plugin_id => $plugin_definition) {
      // Add the plugin to the list.
      $options[$plugin_id] = String::checkPlain($plugin_definition['label']);
    }
    return $options;
  }

}
