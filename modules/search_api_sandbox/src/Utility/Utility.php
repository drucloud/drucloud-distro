<?php

/**
 * @file
 * Deines the class Drupal\search_api\Utility\Utility.
 */

namespace Drupal\search_api\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Item\AdditionalField;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;

/**
 * Contains utility methods for the Search API.
 *
 * @todo Maybe move some of these methods to other classes (and/or split this
 *   class into several utility classes.
 */
class Utility {

  /**
   * Static cache for field type mapping.
   *
   * @var array
   *
   * @see getFieldTypeMapping()
   */
  static $fieldTypeMapping = array();

  /**
   * Determines whether a field of the given type contains text data.
   *
   * @param string $type
   *   A string containing the type to check.
   * @param array $text_types
   *   Optionally, an array of types to be considered as text.
   *
   * @return bool
   *   TRUE if $type is either one of the specified types, or a list of such
   *   values. FALSE otherwise.
   */
  // @todo Currently, this is useless, but later we could also check
  //   automatically for custom types that have one of the passed types as their
  //   fallback.
  static function isTextType($type, array $text_types = array('text')) {
    return in_array($type, $text_types);
  }

  /**
   * Returns all field data types known by the Search API as an options list.
   *
   * @return array
   *   An associative array with all recognized types as keys, mapped to their
   *   translated display names.
   *
   * @see \Drupal\search_api\Utility::getDefaultDataTypes()
   * @see \Drupal\search_api\Utility::getDataTypeInfo()
   */
  // @todo Rename to something more self-documenting, like getDataTypeOptions().
  static function getDataTypes() {
    $types = self::getDefaultDataTypes();
    foreach (self::getDataTypeInfo() as $id => $type) {
      $types[$id] = $type['name'];
    }

    return $types;
  }

  /**
   * Retrieves the mapping for known data types to Search API's internal types.
   *
   * @return array
   *   An array mapping all known (and supported) Drupal data types to their
   *   corresponding Search API data types. Empty values mean that fields of
   *   that type should be ignored by the Search API.
   *
   * @see hook_search_api_field_type_mapping_alter()
   */
  static function getFieldTypeMapping() {
    // Check the static cache first.
    if (empty(static::$fieldTypeMapping)) {
      // It's easier to write and understand this array in the form of
      // $search_api_field_type => array($data_types) and flip it below.
      $default_mapping = array(
        'text' => array(
          'field_item:string_long.string',
          'field_item:text_long.string',
          'field_item:text_with_summary.string',
        ),
        'string' => array(
          'string',
          'email',
          'uri',
          'filter_format',
          'duration_iso8601,'
        ),
        'integer' => array(
          'integer',
          'timespan',
        ),
        'decimal' => array(
          'decimal',
          'float',
        ),
        'date' => array(
          'datetime_iso8601',
          'timestamp',
        ),
        'boolean' => array(
          'boolean',
        ),
        // Types we know about but want/have to ignore.
        NULL => array(
          'language',
        ),
      );

      foreach ($default_mapping as $search_api_type => $data_types) {
        foreach ($data_types as $data_type) {
          $mapping[$data_type] = $search_api_type;
        }
      }

      // Allow other modules to intercept and define what default type they want
      // to use for their data type.
      \Drupal::moduleHandler()->alter('search_api_field_type_mapping', $mapping);

      static::$fieldTypeMapping = $mapping;
    }

    return static::$fieldTypeMapping;
  }

  /**
   * Returns the default field types recognized by the Search API framework.
   *
   * @return array
   *   An associative array with the default types as keys, mapped to their
   *   translated display names.
   */
  static function getDefaultDataTypes() {
    return array(
      'text' => \Drupal::translation()->translate('Fulltext'),
      'string' => \Drupal::translation()->translate('String'),
      'integer' => \Drupal::translation()->translate('Integer'),
      'decimal' => \Drupal::translation()->translate('Decimal'),
      'date' => \Drupal::translation()->translate('Date'),
      'boolean' => \Drupal::translation()->translate('Boolean'),
    );
  }

  /**
   * Returns either all custom field type definitions, or a specific one.
   *
   * @param $type
   *   If specified, the type whose definition should be returned.
   *
   * @return array
   *   If $type was not given, an array containing all custom data types, in the
   *   format specified by hook_search_api_data_type_info().
   *   Otherwise, the definition for the given type, or NULL if it is unknown.
   *
   * @see hook_search_api_data_type_info()
   */
  static function getDataTypeInfo($type = NULL) {
    $types = &drupal_static(__FUNCTION__);
    if (!isset($types)) {
      $default_types = Utility::getDefaultDataTypes();
      $types =  \Drupal::moduleHandler()->invokeAll('search_api_data_type_info');
      $types = $types ? $types : array();
      foreach ($types as &$type_info) {
        if (!isset($type_info['fallback']) || !isset($default_types[$type_info['fallback']])) {
          $type_info['fallback'] = 'string';
        }
      }
      \Drupal::moduleHandler()->alter('search_api_data_type_info', $types);
    }
    if (isset($type)) {
      return isset($types[$type]) ? $types[$type] : NULL;
    }
    return $types;
  }

  /**
   * Extracts specific field values from a complex data object.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $item
   *   The item from which fields should be extracted.
   * @param \Drupal\search_api\Item\FieldInterface[] $fields
   *   The field objects into which data should be extracted, keyed by their
   *   property paths on $item.
   */
  static function extractFields(ComplexDataInterface $item, array $fields) {
    // Figure out which fields are directly on the item and which need to be
    // extracted from nested items.
    $direct_fields = array();
    $nested_fields = array();
    foreach (array_keys($fields) as $key) {
      if (strpos($key, ':') !== FALSE) {
        list($direct, $nested) = explode(':', $key, 2);
        $nested_fields[$direct][$nested] = $fields[$key];
      }
      else {
        $direct_fields[] = $key;
      }
    }
    // Extract the direct fields.
    foreach ($direct_fields as $key) {
      try {
        self::extractField($item->get($key), $fields[$key]);
      }
      catch (\InvalidArgumentException $e) {
        // This can happen with properties added by processors.
        // @todo Find a cleaner solution for this.
      }
    }
    // Recurse for all nested fields.
    foreach ($nested_fields as $direct => $fields_nested) {
      try {
        $item_nested = $item->get($direct);
        if ($item_nested instanceof DataReferenceInterface) {
          $item_nested = $item_nested->getTarget();
        }
        if ($item_nested instanceof EntityInterface) {
          $item_nested = $item_nested->getTypedData();
        }
        if ($item_nested instanceof ComplexDataInterface && !$item_nested->isEmpty()) {
          self::extractFields($item_nested, $fields_nested);
        }
        elseif ($item_nested instanceof ListInterface && !$item_nested->isEmpty()) {
          foreach ($item_nested as $list_item) {
            self::extractFields($list_item, $fields_nested);
          }
        }
      }
      catch (\InvalidArgumentException $e) {
        // This can happen with properties added by processors.
        // @todo Find a cleaner solution for this.
      }
    }
  }

  /**
   * Extracts value and original type from a single piece of data.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The piece of data from which to extract information.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field into which to put the extracted data.
   */
  static function extractField(TypedDataInterface $data, FieldInterface $field) {
    if ($data->getDataDefinition()->isList()) {
      foreach ($data as $piece) {
        self::extractField($piece, $field);
      }
      return;
    }
    $value = $data->getValue();
    $definition = $data->getDataDefinition();
    if ($definition instanceof ComplexDataDefinitionInterface) {
      $property = $definition->getMainPropertyName();
      if (isset($value[$property])) {
        $field->addValue($value[$property]);
      }
    }
    else {
      $field->addValue(reset($value));
    }
    $field->setOriginalType($definition->getDataType());
  }

  /**
   * Retrieves the server task manager to use.
   *
   * @return \Drupal\search_api\Task\ServerTaskManagerInterface
   *   The server task manager to use.
   */
  public static function getServerTaskManager() {
    return \Drupal::service('entity.search_api_server.task_manager');
  }

  /**
   * Creates a new search query object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index on which to search.
   * @param array $options
   *   (optional) The options to set for the query. See
   *   \Drupal\search_api\Query\QueryInterface::setOption() for a list of
   *   options that are recognized by default.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A search query object to use.
   *
   * @see \Drupal\search_api\Query\QueryInterface::create()
   */
  public static function createQuery(IndexInterface $index, array $options = array()) {
    return Query::create($index, $options);
  }

  /**
   * Creates a new search result set.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The executed search query.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   A search result set for the given query.
   */
  public static function createSearchResultSet(QueryInterface $query) {
    return new ResultSet($query);
  }

  /**
   * Creates a Search API item.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The item's search index.
   * @param string $id
   *   The item's (combined) ID.
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource of the item. If not set, it will be determined
   *   from the ID and loaded from the index if needed.
   *
   * @return \Drupal\search_api\Item\ItemInterface
   *   A Search API item with the given values.
   */
  public static function createItem(IndexInterface $index, $id, DatasourceInterface $datasource = NULL) {
    return new Item($index, $id, $datasource);
  }

  /**
   * Creates a Search API item by wrapping an existing complex data object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The item's search index.
   * @param \Drupal\Core\TypedData\ComplexDataInterface $original_object
   *   The original object to wrap.
   * @param string $id
   *   (optional) The item's (combined) ID. If not set, it will be determined
   *   with the \Drupal\search_api\Datasource\DatasourceInterface::getItemId()
   *   method of $datasource. In this case, $datasource must not be NULL.
   * @param \Drupal\search_api\Datasource\DatasourceInterface|null $datasource
   *   (optional) The datasource of the item. If not set, it will be determined
   *   from the ID and loaded from the index if needed.
   *
   * @return \Drupal\search_api\Item\ItemInterface
   *   A Search API item with the given values.
   *
   * @throws \InvalidArgumentException
   *   If both $datasource and $id are NULL.
   */
  public static function createItemFromObject(IndexInterface $index, ComplexDataInterface $original_object, $id = NULL, DatasourceInterface $datasource = NULL) {
    if (!isset($id)) {
      if (!isset($datasource)) {
        throw new \InvalidArgumentException('Need either an item ID or the datasource to create a search item from an object.');
      }
      $id = self::createCombinedId($datasource->getPluginId(), $datasource->getItemId($original_object));
    }
    $item = static::createItem($index, $id, $datasource);
    $item->setOriginalObject($original_object);
    return $item;
  }

  /**
   * Creates a new field object wrapping a field of the given index.
   *
   * @param IndexInterface $index
   *   The index to which this field should be attached.
   * @param string $field_identifier
   *   The field identifier.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   An object containing information about the field on the given index.
   */
  public static function createField(IndexInterface $index, $field_identifier) {
    return new Field($index, $field_identifier);
  }

  /**
   * Creates a new field object wrapping an additional field of the given index.
   *
   * @param IndexInterface $index
   *   The index to which this field should be attached.
   * @param string $field_identifier
   *   The field identifier.
   *
   * @return \Drupal\search_api\Item\AdditionalFieldInterface
   *   An object containing information about the additional field on the given
   *   index.
   */
  public static function createAdditionalField(IndexInterface $index, $field_identifier) {
    return new AdditionalField($index, $field_identifier);
  }

  /**
   * Creates a single token for the "tokenized_text" type.
   *
   * @param string $value
   *   The word or other token value.
   * @param float $score
   *   (optional) The token's score.
   *
   * @return array
   *   An array with appropriate "value" and "score" keys set.
   */
  public static function createTextToken($value, $score = 1.0) {
    return array(
      'value' => $value,
      'score' => (float) $score,
    );
  }

  /**
   * Returns a deep copy of the input array.
   *
   * The behavior of PHP regarding arrays with references pointing to it is
   * rather weird.
   *
   * @param array $array
   *   The array to copy.
   *
   * @return array
   *   A deep copy of the array.
   */
  public static function deepCopy(array $array) {
    $copy = array();
    foreach ($array as $k => $v) {
      if (is_array($v)) {
        $copy[$k] = static::deepCopy($v);
      }
      elseif (is_object($v)) {
        $copy[$k] = clone $v;
      }
      elseif ($v) {
        $copy[$k] = $v;
      }
    }
    return $copy;
  }

  /**
   * Creates a combined ID from a raw ID and an optional datasource prefix.
   *
   * This can be used to created an internal item ID or field identifier from a
   * datasource ID and a datasource-specific raw item ID or property path.
   *
   * @param string|null $datasource_id
   *   If NULL, the returned ID should be that for a datasource-independent
   *   field. Otherwise, the ID of the datasource to which the item or field
   *   belongs.
   * @param string $raw_id
   *   The datasource-specific raw item ID or property path of the item or
   *   field.
   *
   * @return string
   *   The combined ID, with optional datasource prefix separated by
   *   \Drupal\search_api\Index\IndexInterface::DATASOURCE_ID_SEPARATOR.
   */
  public static function createCombinedId($datasource_id, $raw_id) {
    if (!isset($datasource_id)) {
      return $raw_id;
    }
    return $datasource_id . IndexInterface::DATASOURCE_ID_SEPARATOR . $raw_id;
  }

  /**
   * Splits an internal ID into its two parts.
   *
   * Both internal item IDs and internal field identifiers are prefixed with the
   * corresponding datasource ID. This method will split these IDs up again into
   * their two parts.
   *
   * @param string $combined_id
   *   The internal ID, with an optional datasource prefix separated with
   *   \Drupal\search_api\Index\IndexInterface::DATASOURCE_ID_SEPARATOR from the
   *   raw item ID or property path.
   *
   * @return array
   *   A numeric array, containing the datasource ID in element 0 and the raw
   *   item ID or property path in element 1. In the case of
   *   datasource-independent fields (i.e., when there is no prefix), element 0
   *   will be NULL.
   */
  public static function splitCombinedId($combined_id) {
    $pos = strpos($combined_id, IndexInterface::DATASOURCE_ID_SEPARATOR);
    if ($pos === FALSE) {
      return array(NULL, $combined_id);
    }
    return array(substr($combined_id, 0, $pos), substr($combined_id, $pos + 1));
  }

}
