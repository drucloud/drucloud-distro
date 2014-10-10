<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Processor\ProcessorTestBase.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Search API Processor tests base class.
 */
abstract class ProcessorTestBase extends EntityUnitTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  public static $modules = array('user', 'node', 'search_api','search_api_db', 'search_api_test_backend', 'comment', 'entity_reference');

  /**
   * The processor used for these tests.
   *
   * @var \Drupal\search_api\Processor\ProcessorInterface
   */
  protected $processor;

  /**
   * @var \Drupal\search_api\Entity\Index
   */
  protected $index;

  /**
   * @var \Drupal\search_api\Entity\Server
   */
  protected $server;

  /**
   * Creates a new processor object for use in the tests.
   */
  public function setUp($processor = NULL) {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', array('node_access'));
    $this->installEntitySchema('comment');
    $this->installSchema('search_api', array('search_api_item', 'search_api_task'));

    $server_name = $this->randomMachineName();
    $this->server = entity_create('search_api_server', array(
      'machine_name' => strtolower($server_name),
      'name' => $server_name,
      'status' => TRUE,
      'backend' => 'search_api_db',
      'backend_config' => array(
        'min_chars' => 3,
        'database' => 'default:default',
      ),
    ));
    $this->server->save();

    $index_name = $this->randomMachineName();
    $this->index = entity_create('search_api_index', array(
      'machine_name' => strtolower($index_name),
      'name' => $index_name,
      'status' => TRUE,
      'datasources' => array('entity:comment', 'entity:node'),
      'server' => $server_name,
      'tracker' => 'default_tracker',
    ));
    $this->index->setServer($this->server);
    $this->index->setOption('fields', array(
      'entity:comment|subject' => array(
        'type' => 'text',
      ),
      'entity:comment|status' => array(
        'type' => 'boolean',
      ),
      'entity:node|title' => array(
        'type' => 'text',
      ),
      'entity:node|author' => array(
        'type' => 'integer',
      ),
      'entity:node|status' => array(
        'type' => 'boolean',
      ),
    ));
    if ($processor) {
      $this->index->setOption('processors', array(
        $processor => array(
          'status' => TRUE,
          'weight' => 0,
        ),
      ));
    }
    $this->index->save();

    /** @var \Drupal\search_api\Processor\ProcessorPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.search_api.processor');
    $this->processor = $plugin_manager->createInstance($processor, array('index' => $this->index));
  }

  /**
   * Populates testing items.
   *
   * @param array $items
   *   Data to populate test items.
   *   - datasource: The datasource plugin id.
   *   - item: The item object to be indexed.
   *   - item_id: Datasource-specific raw item id.
   *   - text: Textual value of the test field.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   The populated test items.
   */
  public function generateItems(array $items) {
    /** @var \Drupal\search_api\Item\ItemInterface[] $extracted_items */
    $extracted_items = array();
    foreach ($items as $item) {
      $id = $item['datasource'] . IndexInterface::DATASOURCE_ID_SEPARATOR . $item['item_id'];
      $extracted_items[$id] = Utility::createItemFromObject($this->index, $item['item'], $id);
      foreach (array(NULL, $item['datasource']) as $datasource_id) {
        foreach ($this->index->getFieldsByDatasource($datasource_id) as $key => $field) {
          /** @var \Drupal\search_api\Item\FieldInterface $field */
          $field = clone $field;
          if (isset($item[$field->getPropertyPath()])) {
            $field->addValue($item[$field->getPropertyPath()]);
          }
          $extracted_items[$id]->setField($key, $field);
        }
      }
    }

    return $extracted_items;
  }

}
