<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Processor\ContentAccessTest.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\comment\Entity\CommentType;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Query\Query;

/**
 * Tests the "Content access" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\ContentAccess
 */
class ContentAccessTest extends ProcessorTestBase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\ContentAccess
   */
  protected $processor;

  /**
   * @var \Drupal\comment\Entity\Comment[]
   */
  protected $comments;

  /**
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp() {
    parent::setUp('content_access');

    $this->installSchema('comment', array('comment_entity_statistics'));

    // Create a node type for testing.
    $type = entity_create('node_type', array('type' => 'page', 'name' => 'page'));
    $type->save();

    // Create anonymous user name.
    $role = entity_create('user_role', array(
      'id' => 'anonymous',
      'label' => 'anonymous',
    ));
    $role->save();

    // Insert anonymous user into the database as the user table is inner joined
    // by the CommentStorage.
    entity_create('user', array(
      'uid' => 0,
      'name' => '',
    ))->save();

    // Create a node with attached comment.
    $this->nodes[0] = entity_create('node', array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[0]->save();

    $comment_type = CommentType::create(array(
      'id' => 'comment',
      'target_entity_type_id' => 'node',
    ));
    $comment_type->save();

    $this->container->get('comment.manager')->addDefaultField('node', 'page');

    $comment = entity_create('comment', array(
      'entity_type' => 'node',
      'entity_id' => $this->nodes[0]->id(),
      'field_name' => 'comment',
      'body' => 'test body',
      'comment_type' => $comment_type->id(),
    ));
    $comment->save();

    $this->comments[] = $comment;

    $this->nodes[1] = entity_create('node', array('status' => NODE_PUBLISHED, 'type' => 'page', 'title' => 'test title'));
    $this->nodes[1]->save();

    $fields = $this->index->getOption('fields');
    $fields['entity:node|search_api_node_grants'] = array(
      'type' => 'string',
    );
    $fields['entity:comment|search_api_node_grants'] = array(
      'type' => 'string',
    );
    $this->index->setOption('fields', $fields);
    $this->index->save();

    $this->index = entity_load('search_api_index', $this->index->id(), TRUE);
  }

  /**
   * Tests building the query when content is accessible to all.
   */
  public function testQueryAccessAll() {
    user_role_grant_permissions('anonymous', array('access content', 'access comments'));
    $this->index->index();
    $query = Query::create($this->index);
    $result = $query->execute();

    $this->assertEqual($result->getResultCount(), 2);
  }

  /**
   * Tests building the query when content is accessible based on node grants.
   */
  public function testQueryAccessWithNodeGrants() {
    // Create user that will be passed into the query.
    $authenticated_user = $this->createUser(array('uid' => 2), array('access content'));

    db_insert('node_access')
      ->fields(array(
        'nid' => $this->nodes[0]->id(),
        'langcode' => $this->nodes[0]->language()->id,
        'gid' => $authenticated_user->id(),
        'realm' => 'search_api_test',
        'grant_view' => 1,
      ))
      ->execute();

    $this->index->index();
    $query = Query::create($this->index);
    $query->setOption('search_api_access_account', $authenticated_user);
    $result = $query->execute();

    $this->assertEqual($result->getResultCount(), 1);
  }

  /**
   *  Test scenario all users have access to content.
   */
  public function testContentAccessAll() {
    user_role_grant_permissions('anonymous', array('access content', 'access comments'));
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'text' => $this->randomMachineName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);

    $field_id = 'entity:comment' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'search_api_node_grants';
    foreach ($items as $item) {
      $this->assertEqual($item->getField($field_id)->getValues(), array('node_access__all'));
    }
  }

  /**
   * Tests scenario where hook_search_api_node_grants() take effect.
   */
  public function testContentAccessWithNodeGrants() {
    $items = array();
    foreach ($this->comments as $comment) {
      $items[] = array(
        'datasource' => 'entity:comment',
        'item' => $comment->getTypedData(),
        'item_id' => $comment->id(),
        'field_text' => $this->randomMachineName(),
      );
    }
    $items = $this->generateItems($items);

    $this->processor->preprocessIndexItems($items);

    $field_id = 'entity:comment' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'search_api_node_grants';
    foreach ($items as $item) {
      $this->assertEqual($item->getField($field_id)->getValues(), array('node_access_search_api_test:0'));
    }
  }

}
