<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api\Plugin\Processor\NodeStatusTest.
 */

namespace Drupal\Tests\search_api\Plugin\Processor;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Node status" processor.
 *
 * @group search_api
 *
 * @var \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus
 */
class NodeStatusTest extends UnitTestCase {

  /**
   * Stores the processor to be tested.
   *
   * @var \Drupal\search_api\Plugin\SearchApi\Processor\NodeStatus
   */
  protected $processor;

  /**
   * The test items to use.
   *
   * @var \Drupal\search_api\Item\ItemInterface[]
   */
  protected $items = array();

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = new NodeStatus(array(), 'node_status', array());

    $datasource = $this->getMock('Drupal\search_api\Datasource\DatasourceInterface');
    $datasource->expects($this->any())
      ->method('getEntityTypeId')
      ->will($this->returnValue('node'));
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */

    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');
    $index->expects($this->any())
      ->method('getDatasources')
      ->will($this->returnValue(array($datasource)));
      /** @var \Drupal\search_api\Index\IndexInterface $index */

    $item = Utility::createItem($index, 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '1:en', $datasource);
    $unpublished_node = $this->getMockBuilder('Drupal\Tests\search_api\TestNodeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $unpublished_node->expects($this->any())
      ->method('isPublished')
      ->will($this->returnValue(FALSE));
    /** @var \Drupal\node\NodeInterface $unpublished_node */

    $item->setOriginalObject(EntityAdapter::createFromEntity($unpublished_node));
    $this->items[$item->getId()] = $item;

    $item = Utility::createItem($index, 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '2:en', $datasource);
    $published_node = $this->getMockBuilder('Drupal\Tests\search_api\TestNodeInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $published_node->expects($this->any())
      ->method('isPublished')
      ->will($this->returnValue(TRUE));
    /** @var \Drupal\node\NodeInterface $published_node */

    $item->setOriginalObject(EntityAdapter::createFromEntity($published_node));
    $this->items[$item->getId()] = $item;
  }

  /**
   * Tests whether supportsIndex() returns TRUE for an index cotnaining nodes.
   */
  public function testSupportsIndexSupported() {
    $support = NodeStatus::supportsIndex(reset($this->items)->getIndex());
    $this->assertTrue($support, 'Index containing a node datasource is supported.');
  }

  /**
   * Tests whether supportsIndex() returns TRUE for an index cotnaining nodes.
   */
  public function testSupportsIndexUnsupported() {
    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');
    $index->expects($this->any())
      ->method('getDatasources')
      ->will($this->returnValue(array()));
    /** @var \Drupal\search_api\Index\IndexInterface $index */
    $support = NodeStatus::supportsIndex($index);
    $this->assertFalse($support, 'Index containing no node datasource is not supported.');
  }

  /**
   * Tests is unpublished nodes are removed from the items list.
   */
  public function testNodeStatus() {
    $this->assertCount(2, $this->items, '2 nodes in the index.');
    $this->processor->preprocessIndexItems($this->items);

    $this->assertCount(1, $this->items, 'An item was removed from the items list.');
    $published_nid = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '2:en';
    $this->assertTrue(isset($this->items[$published_nid]), 'Correct item was removed.');
  }

}
