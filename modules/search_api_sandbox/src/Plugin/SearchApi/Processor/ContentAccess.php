<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\ContentAccess
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SearchApiProcessor(
 *   id = "content_access",
 *   label = @Translation("Content access"),
 *   description = @Translation("Adds content access checks for nodes and comments.")
 * )
 */
class ContentAccess extends ProcessorPluginBase {

  /**
   * The logger to use for logging messages.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\search_api\Plugin\SearchApi\Processor\ContentAccess $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Logger\LoggerChannelInterface $logger */
    $logger = $container->get('logger.factory')->get('search_api');
    $processor->setLogger($logger);

    return $processor;
  }

  /**
   * Retrieves the logger to use.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ?: \Drupal::service('logger.factory')->get('search_api');
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
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), array('node', 'comment'))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo - here we need to secure that the author and published fields are
    //   being indexed as we need them for the access filter.

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function alterPropertyDefinitions(array &$properties, DatasourceInterface $datasource = NULL) {
    if (!$datasource) {
      return;
    }

    if (in_array($datasource->getEntityTypeId(), array('node', 'comment'))) {
      $definition = array(
        'label' => $this->t('Node access information'),
        'description' => $this->t('Data needed to apply node access.'),
        'type' => 'string',
      );
      $properties['search_api_node_grants'] = new DataDefinition($definition);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    static $anonymous_user;

    if (!isset($anonymous_user)) {
      // Load the anonymous user.
      $anonymous_user = new AnonymousUserSession();
    }

    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      // Only run for node and comment items.
      if (!in_array($item->getDatasource()->getEntityTypeId(), array('node', 'comment'))) {
        continue;
      }

      // Bail if the field is not indexed.
      $field_id = Utility::createCombinedId($item->getDatasourceId(), 'search_api_node_grants');
      if (!($field = $item->getField($field_id))) {
        continue;
      }

      // Get the node object.
      $node = $this->getNode($item->getOriginalObject());
      if (!$node) {
        // Apparently we were active for a wrong item.
        continue;
      }

      // Collect grant information for the node.
      if (!$node->access('view', $anonymous_user)) {
        // If anonymous user has no permission we collect all grants with their
        // realms in the item.
        $result = db_query('SELECT * FROM {node_access} WHERE (nid = 0 OR nid = :nid) AND grant_view = 1', array(':nid' => $node->id()));
        foreach ($result as $grant) {
          $field->addValue("node_access_{$grant->realm}:{$grant->gid}");
        }
      }
      else {
        // Add the generic pseudo view grant if we are not using node access or
        // the node is viewable by anonymous users.
        $field->addValue('node_access__all');
      }
    }
  }

  /**
   * Retrieves the node related to an indexed search object.
   *
   * Will be either the node itself, or the node the comment is attached to.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   A search object that is being indexed.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node related to that search object.
   */
  protected function getNode(ComplexDataInterface $item) {
    $item = $item->getValue();
    if ($item instanceof CommentInterface) {
      $item = $item->getCommentedEntity();
    }
    if ($item instanceof NodeInterface) {
      return $item;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    if (!$query->getOption('search_api_bypass_access')) {
      $account = $query->getOption('search_api_access_account', \Drupal::currentUser());
      if (is_numeric($account)) {
        $account = entity_load('user', $account);
      }
      if (is_object($account)) {
        try {
          $this->addNodeAccess($query, $account);
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
      }
      else {
        $account = $query->getOption('search_api_access_account', \Drupal::currentUser());
        if ($account instanceof AccountInterface) {
          $account = $account->id();
        }
        if (!is_scalar($account)) {
          $account = var_export($account, TRUE);
        }
        $this->getLogger()->warning('An illegal user UID was given for node access: @uid.', array('@uid' => $account));
      }
    }
  }

  /**
   * Adds a node access filter to a search query, if applicable.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to which a node access filter should be added, if applicable.
   * @param \Drupal\core\Session\AccountInterface $account
   *   The user for whom the search is executed.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If not all necessary fields are indexed on the index.
   */
  protected function addNodeAccess(QueryInterface $query, AccountInterface $account) {
    // Don't do anything if the user can access all content.
    if ($account->hasPermission('bypass node access')) {
      return;
    }

    // @todo This is completely wrong. We shouldn't hardcode any datasource IDs,
    //   but collect them according to contained entity type, then add filters
    //   for each of those and its fields combined with an "or some other
    //   datasource" condition. Or maybe the field should just be datasource-
    //   independent? Probably makes more sense, since the conditions are the
    //   same anyways.
    // @todo Also definitely needs tests that this is working correctly with
    //   items of different datasources mixed together.

    // Get the fields that are being indexed.
    $fields = $query->getIndex()->getOption('fields');
    // Define required fields that need to be part of the index.
    $required = array('entity:node|search_api_node_grants', 'entity:node|status');

    $datasources_filter = $query->createFilter('OR');

    // Go through the datasources and add conditions for status and author.
    foreach ($this->index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
        $node_filter = $query->createFilter('OR');
        $node_filter->condition('entity:node|status', NODE_PUBLISHED);
        if (\Drupal::currentUser()->hasPermission('view own unpublished content')) {
          $node_filter->condition('entity:node|author', $account->id());
        }
        $datasources_filter->filter($node_filter);
        // Add author as one of the required fields as we need to add query
        // condition for the field.
        $required[] = 'entity:node|author';
      }
      elseif ($datasource->getEntityTypeId() == 'comment') {
        $datasources_filter->condition('entity:comment|status', Comment::PUBLISHED);
      }
    }

    $query->filter($datasources_filter);

    // Check if all required fields are present in the index.
    foreach ($required as $field) {
      if (empty($fields[$field])) {
        $vars['@field'] = $field;
        $vars['@index'] = $query->getIndex()->label();
        throw new SearchApiException(String::format('Required field @field not indexed on index @index. Could not perform access checks.', $vars));
      }
    }

    // If the user cannot access content/comments at all, return no results.
    if (!$account->hasPermission('access content')) {
      // Simple hack for returning no results.
      $query->condition('entity:node|status', 0);
      $query->condition('entity:node|status', 1);
      return;
    }

    // Filter by the user's node access grants.
    $node_filter = $query->createFilter('OR');
    $grants = node_access_grants('view', $account);
    foreach ($grants as $realm => $gids) {
      foreach ($gids as $gid) {
        $node_filter->condition('entity:node|search_api_node_grants', "node_access_$realm:$gid");
      }
    }
    // Also add items that are accessible for everyone by checking the "access
    // all" pseudo grant.
    $node_filter->condition('entity:node|search_api_node_grants', 'node_access__all');
    $query->filter($node_filter);
  }

}
