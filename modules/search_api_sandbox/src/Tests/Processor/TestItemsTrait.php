<?php

/**
 * @file
 * Contains \Drupal\search_api\Tests\Plugin\Processor\TestItemsTrait.
 */

namespace Drupal\search_api\Tests\Processor;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Provides common methods for test cases that need to create search items.
 */
trait TestItemsTrait {

  /**
   * The used item IDs for test items.
   *
   * @var array
   */
  protected $item_ids = array();

  /**
   * Creates an array with a single item which has the given field.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index that should be used for the item.
   * @param string $field_type
   *   The field type to set for the field.
   * @param mixed $field_value
   *   A field value to add to the field.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   (optional) A variable, passed by reference, into which the created field
   *   will be saved.
   * @param string|null $field_id
   *   (optional) The field ID to set for the field.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing a single item with the specified field.
   */
  public function createSingleFieldItem(IndexInterface $index, $field_type, $field_value, FieldInterface &$field = NULL, $field_id = NULL) {
    if (!isset($field_id)) {
      $field_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'field_test';
    }
    $this->item_ids[0] = $item_id = 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . '1:en';
    $item = Utility::createItem($index, $item_id);
    $field = Utility::createField($index, 'entity:node' . IndexInterface::DATASOURCE_ID_SEPARATOR . 'field_test');
    $field->setType($field_type);
    $field->addValue($field_value);
    $item->setField($field_id, $field);
    $item->setFieldsExtracted(TRUE);

    return array($item_id => $item);
  }

  /**
   * Creates a certain number of test items.
   *
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index that should be used for the items.
   * @param int $count
   *   The number of items to create.
   * @param array[] $fields
   *   The fields to create on the items, with keys being field IDs and values
   *   being arrays with the following information:
   *   - type: The type to set for the field.
   *   - values: (optional) The values to set for the field.
   * @param \Drupal\Core\TypedData\ComplexDataInterface|null $object
   *   The object to set on each item as the "original object".
   * @param array|null $datasource_ids
   *   An array of datasource IDs to use for the items, in that order (starting
   *   again from the front if necessary). Defaults to only using "entity:node".
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   An array containing the requested test items.
   */
  public function createItems(IndexInterface $index, $count, array $fields, ComplexDataInterface $object = NULL, array $datasource_ids = NULL) {
    if (!isset($datasource_ids)) {
      $datasource_ids = array('entity:node');
    }
    $datasource_count = count($datasource_ids);
    $items = array();
    for ($i = 0; $i < $count; ++$i) {
      $datasource_id = $datasource_ids[$i % $datasource_count];
      $this->item_ids[$i] = $item_id = Utility::createCombinedId($datasource_id, ($i + 1) . ':en');
      $item = Utility::createItem($index, $item_id);
      if (isset($object)) {
        $item->setOriginalObject($object);
      }
      foreach ($fields as $field_id => $field_info) {
        // Only add fields of the right datasource.
        list($field_datasource_id) = Utility::splitCombinedId($field_id);
        if (isset($field_datasource_id) && $field_datasource_id != $datasource_id) {
          continue;
        }
        $field = Utility::createField($index, $field_id)
          ->setType($field_info['type']);
        if (isset($field_info['values'])) {
          $field->setValues($field_info['values']);
        }
        $item->setField($field_id, $field);
      }
      $item->setFieldsExtracted(TRUE);
      $items[$item_id] = $item;
    }
    return $items;
  }

}
