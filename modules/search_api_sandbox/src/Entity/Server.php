<?php

/**
 * @file
 * Contains \Drupal\search_api\Entity\Server.
 */

namespace Drupal\search_api\Entity;

use Drupal\Component\Utility\String;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Server\ServerInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Defines the search server configuration entity.
 *
 * @ConfigEntityType(
 *   id = "search_api_server",
 *   label = @Translation("Search server"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "form" = {
 *       "default" = "Drupal\search_api\Form\ServerForm",
 *       "edit" = "Drupal\search_api\Form\ServerForm",
 *       "delete" = "Drupal\search_api\Form\ServerDeleteConfirmForm",
 *       "disable" = "Drupal\search_api\Form\ServerDisableConfirmForm",
 *       "clear" = "Drupal\search_api\Form\ServerClearConfirmForm"
 *     },
 *   },
 *   admin_permission = "administer search_api",
 *   config_prefix = "server",
 *   entity_keys = {
 *     "id" = "machine_name",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   links = {
 *     "canonical" = "entity.search_api_server.canonical",
 *     "add-form" = "entity.search_api_server.add_form",
 *     "edit-form" = "entity.search_api_server.edit_form",
 *     "delete-form" = "entity.search_api_server.delete_form",
 *     "disable" = "entity.search_api_server.disable",
 *     "enable" = "entity.search_api_server.enable"
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface {

  /**
   * The machine name of the server.
   *
   * @var string
   */
  protected $machine_name;

  /**
   * The displayed name of the server.
   *
   * @var string
   */
  protected $name;

  /**
   * The displayed description of the server.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The ID of the backend plugin.
   *
   * @var string
   */
  protected $backend;

  /**
   * The backend plugin configuration.
   *
   * @var array
   */
  protected $backend_config = array();

  /**
   * The backend plugin instance.
   *
   * @var \Drupal\search_api\Backend\BackendInterface
   */
  protected $backendPluginInstance;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->machine_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidBackend() {
    $backend_plugin_definition = \Drupal::service('plugin.manager.search_api.backend')->getDefinition($this->getBackendId(), FALSE);
    return !empty($backend_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendId() {
    return $this->backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackend() {
    if (!$this->backendPluginInstance) {
      $backend_plugin_manager = \Drupal::service('plugin.manager.search_api.backend');
      $config = $this->backend_config;
      $config['server'] = $this;
      if (!($this->backendPluginInstance = $backend_plugin_manager->createInstance($this->getBackendId(), $config))) {
        $args['@backend'] = $this->getBackendId();
        $args['%server'] = $this->label();
        throw new SearchApiException(t('The backend with ID "@backend" could not be retrieved for server %server.', $args));
      }
    }
    return $this->backendPluginInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendConfig() {
    return $this->backend_config;
  }

  /**
   * {@inheritdoc}
   */
  public function setBackendConfig(array $backend_config) {
    $this->backend_config = $backend_config;
    $this->getBackend()->setConfiguration($backend_config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexes(array $properties = array()) {
    $storage = \Drupal::entityManager()->getStorage('search_api_index');
    return $storage->loadByProperties(array('server' => $this->id()) + $properties);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    return $this->hasValidBackend() ? $this->getBackend()->viewSettings() : array();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFeature($feature) {
    return $this->hasValidBackend() ? $this->getBackend()->supportsFeature($feature) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDatatype($type) {
    return $this->hasValidBackend() ? $this->getBackend()->supportsDatatype($type) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index) {
    $server_task_manager = Utility::getServerTaskManager();
    // When freshly adding an index to a server, it doesn't make any sense to
    // execute possible other tasks for that server/index combination.
    // (removeIndex() is implicit when adding an index which was already added.)
    $server_task_manager->delete(NULL, $this, $index);

    try {
      $this->getBackend()->addIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while adding index %index to server %server: !message in %function (line %line of %file).', $vars);
      $server_task_manager->add($this, __FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index) {
    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->updateIndex($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while updating the fields of index %index on server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index, isset($index->original) ? $index->original : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index) {
    $server_task_manager = Utility::getServerTaskManager();
    // When removing an index from a server, it doesn't make any sense anymore
    // to delete items from it, or react to other changes.
    $server_task_manager->delete(NULL, $this, $index);

    try {
      $this->getBackend()->removeIndex($index);
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => is_object($index) ? $index->label() : $index,
      );
      watchdog_exception('search_api', $e, '%type while removing index %index from server %server: !message in %function (line %line of %file).', $vars);
      $server_task_manager->add($this, __FUNCTION__, $index);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $server_task_manager = Utility::getServerTaskManager();
    if ($server_task_manager->execute($this)) {
      return $this->getBackend()->indexItems($index, $items);
    }
    throw new SearchApiException(t('Could not index items because pending server tasks could not be executed.'));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids) {
    if ($index->isReadOnly()) {
      $vars = array(
        '%index' => $index->label(),
      );
      \Drupal::logger('search_api')->warning('Trying to delete items from index %index which is marked as read-only.', $vars);
      return;
    }

    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteItems($index, $item_ids);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items from server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index, $item_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index) {
    if ($index->isReadOnly()) {
      $vars = array(
        '%index' => $index->label(),
      );
      \Drupal::logger('search_api')->warning('Trying to delete items from index %index which is marked as read-only.', $vars);
      return;
    }

    $server_task_manager = Utility::getServerTaskManager();
    try {
      if ($server_task_manager->execute($this)) {
        $this->getBackend()->deleteAllIndexItems($index);
        return;
      }
    }
    catch (SearchApiException $e) {
      $vars = array(
        '%server' => $this->label(),
        '%index' => $index->label(),
      );
      watchdog_exception('search_api', $e, '%type while deleting items of index %index from server %server: !message in %function (line %line of %file).', $vars);
    }
    $server_task_manager->add($this, __FUNCTION__, $index);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllItems() {
    $failed = array();
    $properties['status'] = TRUE;
    $properties['read_only'] = FALSE;
    foreach ($this->getIndexes($properties) as $index) {
      try {
        $this->getBackend()->deleteAllIndexItems($index);
      }
      catch (SearchApiException $e) {
        $args = array(
          '%index' => $index->label(),
        );
        watchdog_exception('search_api', $e, '%type while deleting all items from index %index: !message in %function (line %line of %file).', $args);
        $failed[] = $index->label();
      }
    }
    if (!empty($e)) {
      $args = array(
        '%server' => $this->label(),
        '@indexes' => implode(', ', $failed),
      );
      $message = String::format('Deleting all items from server %server failed for the following (write-enabled) indexes: @indexes.', $args);
      throw new SearchApiException($message, 0, $e);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    return $this->getBackend()->search($query);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // If the server is being disabled, also disable all its indexes.
    if (!$this->status() && isset($this->original) && $this->original->status()) {
      foreach ($this->getIndexes(array('status' => TRUE)) as $index) {
        /** @var \Drupal\search_api\Index\IndexInterface $index */
        $index->setStatus(FALSE)->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    if ($this->hasValidBackend()) {
      if ($update) {
        $this->getBackend()->postUpdate();
      }
      else {
        $this->getBackend()->postInsert();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    // @todo Would it make more sense to swap the order of these operations?
    //   Setting the indexes to server => NULL will trigger the backend's
    //   removeIndex() method which might save the server – which is bad. We'd
    //   probably need an isBeingDeleted() flag in that case. Otherwise we'd
    //   have to make sure that the index's postSave() method is smart enough to
    //   realize the server isn't there anymore and not log (or throw) any
    //   errors.

    // Remove all indexes on the deleted servers from them.
    $index_ids = \Drupal::entityQuery('search_api_index')
      ->condition('server', array_keys($entities), 'IN')
      ->execute();
    $indexes = \Drupal::entityManager()->getStorage('search_api_index')->loadMultiple($index_ids);
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\Index\IndexInterface $index */
      $index->setServer(NULL);
      $index->setStatus(FALSE);
      $index->save();
    }

    // Iterate through the servers, executing the backends' preDelete() methods
    // and removing all their pending server tasks.
    foreach ($entities as $server) {
      /** @var \Drupal\search_api\Server\ServerInterface $server */
      if ($server->hasValidBackend()) {
        $server->getBackend()->preDelete();
      }
      Utility::getServerTaskManager()->delete(NULL, $server);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // @todo It's a bug that we have to do this. Backend configuration should
    //   always be set via the server's setBackendConfiguration() method,
    //   otherwise the two can diverge causing this and other problems. The
    //   alternative would be to call $server->setBackendConfiguration() in the
    //   backend's setConfiguration() method and use a second $propagate
    //   parameter to avoid an infinite loop. Similar things go for the index's
    //   various plugins. Maybe using PluginBagsInterface is the solution here?
    $properties = parent::toArray();
    if ($this->hasValidBackend()) {
      $properties['backend_config'] = $this->getBackend()->getConfiguration();
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    // Add the backend's dependencies.
    if ($this->hasValidBackend() && ($backend = $this->getBackend())) {
      $this->addDependencies($backend->calculateDependencies());
    }

    return $this->dependencies;
  }

  /**
   * Implements the magic __clone() method.
   *
   * Prevents the backend plugin instance from being cloned.
   */
  public function __clone() {
    $this->backendPluginInstance = NULL;
  }

}
