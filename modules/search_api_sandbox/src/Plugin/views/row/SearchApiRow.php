<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\row\SearchApiRow.
 */

namespace Drupal\search_api\Plugin\views\row;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a row plugin for displaying a result as a rendered item.
 *
 * @ViewsRow(
 *   id = "search_api",
 *   title = @Translation("Rendered Search API item"),
 *   help = @Translation("Displays entity of the matching search API item"),
 * )
 */
// @todo Hide for other, non-Search API base tables.
class SearchApiRow extends RowPluginBase {

  /**
   * The search index.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The logger to use for logging messages.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  // @todo Make this into a trait, with an additional logException() method.
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\search_api\Plugin\views\row\SearchApiRow $row */
    $row = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    $row->setEntityManager($entity_manager);

    /** @var \Drupal\Core\Logger\LoggerChannelInterface $logger */
    $logger = $container->get('logger.factory')->get('search_api');
    $row->setLogger($logger);

    return $row;
  }

  /**
   * Retrieves the entity manager.
   *
   * @return \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  public function getEntityManager() {
    return $this->entityManager ?: \Drupal::entityManager();
  }

  /**
   * Sets the entity manager.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The new entity manager.
   *
   * @return $this
   */
  public function setEntityManager(EntityManagerInterface $entityManager) {
    $this->entityManager = $entityManager;
    return $this;
  }

  /**
   * Retrieves the logger to use.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ? : \Drupal::service('logger.factory')->get('search_api');
  }

  /**
   * Sets the logger to use.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to use.
   */
  public function setLogger(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $base_table = $view->storage->get('base_table');
    if (substr($base_table, 0, 17) !== 'search_api_index_') {
      throw new \InvalidArgumentException(String::format('View %view is not based on Search API but tries to use its row plugin.', array('%view' => $view->storage->label())));
    }
    $index_id = substr($base_table, 17);
    $this->index = $this->getEntityManager()->getStorage('search_api_index')->load($index_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['view_modes'] = array('default' => array());

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $view_modes = $datasource->getViewModes();
      if (!$view_modes) {
        $form['view_modes'][$datasource_id] = array(
          '#type' => 'item',
          '#title' => $this->t('View mode for datasource %name', array('%name' => $datasource->label())),
          '#description' => $this->t("This datasource doesn't have any view modes available. It is therefore not possible to display results of this datasource using this row plugin."),
        );
        continue;
      }
      $form['view_modes'][$datasource_id] = array(
        '#type' => 'select',
        '#options' => $view_modes,
        '#title' => $this->t('View mode for datasource %name', array('%name' => $datasource->label())),
        '#default_value' => key($view_modes),
      );
      if (isset($this->options['view_modes'][$datasource_id])) {
        $form['view_modes'][$datasource_id]['#default_value'] = $this->options['view_modes'][$datasource_id];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $view_modes = array();
    foreach ($this->index->getDatasources() as $datasource_id => $datasource) {
      $view_modes[$datasource_id] = $datasource->getViewModes();
    }
    $summary = array();
    foreach ($this->options['view_modes'] as $datasource_id => $view_mode) {
      if (isset($view_modes[$datasource_id][$view_mode])) {
        $view_mode = $view_modes[$datasource_id][$view_mode];
      }
      $args = array(
        '@datasource' => $this->index->getDatasource($datasource_id)->label(),
        '@view_mode' => $view_mode,
      );
      $summary[] = $this->t('@datasource: @view_mode', $args);
    }
    return $summary ? implode('; ', $summary) : '<em>' . $this->t('No settings') . '</em>';
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $datasource_id = $row->search_api_datasource;
    try {
      $datasource = $this->index->getDatasource($datasource_id);
    }
    catch (SearchApiException $e) {
      $context = array(
        '%datasource' => $datasource_id,
        '%view' => $this->view->storage->label(),
      );
      $this->getLogger()->warning('Item of unknown datasource %datasource returned in view %view.', $context);
      return '';
    }
    if (!isset($this->options['view_modes'][$datasource_id])) {
      $context = array(
        '%datasource' => $datasource->label(),
        '%view' => $this->view->storage->label(),
      );
      $this->getLogger()->warning('No view mode set for datasource %datasource in view %view.', $context);
      return '';
    }
    try {
      $view_mode = $this->options['view_modes'][$datasource_id];
      return $this->index->getDataSource($datasource_id)->viewItem($row->_item, $view_mode);
    }
    catch (SearchApiException $e) {
      watchdog_exception('search_api', $e);
      return '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    // @todo Find a better way to ensure that the item is loaded.
    $this->view->query->addField('_magic');
  }

}
