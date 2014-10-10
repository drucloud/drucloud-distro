<?php

/**
 * @file
 * Contains \Drupal\search_api\IndexListBuilder.
 */

namespace Drupal\search_api;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Server\ServerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a listing of search index entities.
 */
class IndexListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity storage class for the 'search_api_server' entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $serverStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.manager')->getStorage('search_api_server')
    );
  }

  /**
   * Constructs an IndexListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityStorageInterface $server_storage
   *   The entity storage class for the 'search_api_server' entity type.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $server_storage) {
    parent::__construct($entity_type, $storage);

    $this->serverStorage = $server_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $indexes = $this->storage->loadMultiple();
    $servers = $this->serverStorage->loadMultiple();

    $this->sortByStatusThenAlphabetically($indexes);
    $this->sortByStatusThenAlphabetically($servers);

    $server_groups = array();
    foreach ($servers as $server) {
      $server_group = array(
        "server." . $server->id() => $server,
      );

      foreach ($server->getIndexes() as $server_index) {
        $server_group["index." . $server_index->id()] = $server_index;
        // Remove this index which is assigned to a server from the list of all
        // indexes.
        foreach ($indexes as $index_key => $index) {
          if ($index->id() === $server_index->id()) {
            unset($indexes[$index_key]);
          }
        }
      }

      $server_groups["server." . $server->id()] = $server_group;
    }

    return array(
      'servers' => $server_groups,
      'lone_indexes' => $indexes,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);

    $status_icon = array(
      '#theme' => 'image',
      '#uri' => $entity->status() ? 'core/misc/icons/73b355/check.svg' : 'core/misc/icons/ea2800/error.svg',
      '#width' => 18,
      '#height' => 18,
      '#alt' => $entity->status() ? $this->t('Enabled') : $this->t('Disabled'),
    );

    return array(
      'data' => array(
        'type' => array(
          'data' => $entity instanceof ServerInterface ? $this->t('Server') : $this->t('Index'),
          'class' => array('search-api-type'),
        ),
        'title' => array(
          'data' => array(
            '#type' => 'link',
            '#title' => $entity->label(),
            '#suffix' => '<div>' . $entity->get('description') . '</div>',
          ) + $entity->urlInfo('canonical')->toRenderArray(),
          'class' => array('search-api-title'),
        ),
        'status' => array(
          'data' => $status_icon,
          'class' => array('checkbox'),
        ),
        'operations' => $row['operations'],
      ),
      'title' => $this->t('Machine name: @name', array('@name' => $entity->id())),
      'class' => array(
        $entity->getEntityTypeId() . '-' . $entity->id(),
        $entity->status() ? 'search-api-list-enabled' : 'search-api-list-disabled',
        $entity instanceof ServerInterface ? 'search-api-list-server' : 'search-api-list-index',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return array(
      'type' => $this->t('Type'),
      'title' => $this->t('Name'),
      'status' => array(
        'data' => $this->t('Status'),
        'class' => array('checkbox'),
      ),
    ) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if ($entity instanceof IndexInterface) {
      $operations['fields'] = array(
        'title' => $this->t('Fields'),
        'weight' => 20,
        'route_name' => 'entity.search_api_index.fields',
        'route_parameters' => array(
          'search_api_index' => $entity->id(),
        ),
      );
      $operations['filters'] = array(
        'title' => $this->t('Filters'),
        'weight' => 30,
        'route_name' => 'entity.search_api_index.filters',
        'route_parameters' => array(
          'search_api_index' => $entity->id(),
        ),
      );
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_groups = $this->load();
    $list['#type'] = 'container';
    $list['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    $list['servers'] = array(
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => array(),
      '#empty' => $this->t('There are no indexes.'),
      '#attributes' => array(
        'id' => 'search-api-entity-list'
      ),
    );
    foreach ($entity_groups['servers'] as $server_groups) {
      foreach ($server_groups as $entity) {
        $list['servers']['#rows'][$entity->getEntityTypeId() . "." . $entity->id()] = $this->buildRow($entity);
      }
    }

    // Output the list of indexes without a server separately.
    if (!empty($entity_groups['lone_indexes'])) {
      $list['lone_indexes']['heading']['#markup'] = '<h3>' . $this->t('Indexes not currently associated with any server') . '</h3>';
      $list['lone_indexes']['table'] = array(
        '#type' => 'table',
        '#header' => $this->buildHeader(),
        '#rows' => array(),
      );

      foreach ($entity_groups['lone_indexes'] as $entity) {
        $list['lone_indexes']['table']['#rows'][$entity->id()] = $this->buildRow($entity);
      }
    }

    return $list;
  }

  /**
   * Sorts an array of entities by status and then alphabetically.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface[] $entities
   *   An array of ConfigEntityBase entities.
   */
  protected function sortByStatusThenAlphabetically(array &$entities) {
    usort($entities, function (ConfigEntityInterface $a, ConfigEntityInterface $b) {
      if ($a->status() == $b->status()) {
        return $a->label() > $b->label();
      }
      else {
        return $a->status() ? -1 : 1;
      }
    });
  }

}
