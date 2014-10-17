<?php

/**
 * @file
 * Contains \Drupal\search_api\Backend\BackendPluginBase.
 */

namespace Drupal\search_api\Backend;

use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api\Server\ServerInterface;

/**
 * Defines a base class for backend plugins.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. These definition arrays may be altered through
 * hook_search_api_backend_info_alter(). The definition includes the following
 * keys:
 * - id: The unique, system-wide identifier of the backend class.
 * - label: The human-readable name of the backend class, translated.
 * - description: A human-readable description for the backend class,
 *   translated.
 *
 * A complete sample plugin definition should be defined as in this example:
 *
 * @code
 * @SearchApiBackend(
 *   id = "my_backend",
 *   label = @Translation("My backend"),
 *   description = @Translation("Searches with SuperSearch™.")
 * )
 * @endcode
 *
 * @see \Drupal\search_api\Annotation\SearchApiBackend
 * @see \Drupal\search_api\Backend\BackendPluginManager
 * @see \Drupal\search_api\Backend\BackendInterface
 * @see plugin_api
 */
abstract class BackendPluginBase extends ConfigurablePluginBase implements BackendInterface {

  /**
   * The server this backend is configured for.
   *
   * @var \Drupal\search_api\Server\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (!empty($configuration['server']) && $configuration['server'] instanceof ServerInterface) {
      $this->setServer($configuration['server']);
      unset($configuration['server']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getServer() {
    return $this->server;
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(ServerInterface $server) {
    $this->server = $server;
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDatatype($type) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function postInsert() {}

  /**
   * {@inheritdoc}
   */
  public function preUpdate() {}

  /**
   * {@inheritdoc}
   */
  public function postUpdate() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete() {
    try {
      $this->getServer()->deleteAllItems();
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->getServer()->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: !message in %function (line %line of %file).', $vars);
      drupal_set_message($this->t('Deleting some of the items on the server failed. Check the logs for details. The server was still removed.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {}

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {}

}
