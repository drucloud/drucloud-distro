<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\FieldTrait.
 */

namespace Drupal\search_api\Item;

use Drupal\Component\Utility\String;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Index\IndexInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Provides a trait for classes wrapping a specific field on an index.
 *
 * @see \Drupal\search_api\Item\GenericFieldInterface
 */
trait FieldTrait {

  /**
   * The index this field is attached to.
   *
   * @var \Drupal\search_api\Index\IndexInterface
   */
  protected $index;

  /**
   * The ID of the index this field is attached to.
   *
   * This is only used to avoid serialization of the index in __sleep() and
   * __wakeup().
   *
   * @var string
   */
  protected $index_id;

  /**
   * The field's identifier.
   *
   * @var string
   */
  protected $fieldIdentifier;

  /**
   * The field's datasource's ID.
   *
   * @var string|null
   */
  protected $datasource_id;

  /**
   * The field's datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface|null
   */
  protected $datasource;

  /**
   * The property path on the search object.
   *
   * @var string
   */
  protected $propertyPath;

  /**
   * This field's data definition.
   *
   * @var \Drupal\Core\TypedData\DataDefinitionInterface
   */
  protected $dataDefinition;

  /**
   * The human-readable label for this field.
   *
   * @var string
   */
  protected $label;

  /**
   * The human-readable description for this field.
   *
   * @var string|null
   */
  protected $description;

  /**
   * The human-readable label for this field's datasource.
   *
   * @var string
   */
  protected $labelPrefix;

  /**
   * Constructs a FieldTrait object.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The field's index.
   * @param string $field_identifier
   *   The field's combined identifier, with datasource prefix if applicable.
   */
  public function __construct(IndexInterface $index, $field_identifier) {
    $this->index = $index;
    $this->fieldIdentifier = $field_identifier;
    list($this->datasource_id, $this->propertyPath) = Utility::splitCombinedId($field_identifier);
  }

  /**
   * Returns the index of this field.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The index to which this field belongs.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getIndex()
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * Returns the index of this field.
   *
   * This is useful when retrieving fields from cache, to have the index always
   * set to the same object that is returning them. The method shouldn't be used
   * in any other case.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index to which this field belongs.
   *
   * @return self
   *   The invoked object.
   *
   * @throws \InvalidArgumentException
   *   If the ID of the given index is not the same as the ID of the index that
   *   was set up to now.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::setIndex()
   */
  public function setIndex(IndexInterface $index) {
    if ($this->index->id() != $index->id()) {
      throw new \InvalidArgumentException('Attempted to change the index of a field object.');
    }
    $this->index = $index;
    return $this;
  }

  /**
   * Returns the field identifier of this field.
   *
   * @return string
   *   The identifier of this field.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getFieldIdentifier()
   */
  public function getFieldIdentifier() {
    return $this->fieldIdentifier;
  }

  /**
   * Retrieves the ID of this field's datasource.
   *
   * @return string|null
   *   The plugin ID of this field's datasource, or NULL if the field is
   *   datasource-independent.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getDatasourceId()
   */
  public function getDatasourceId() {
    return $this->datasource_id;
  }

  /**
   * Returns the datasource of this field.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface|null
   *   The datasource to which this field belongs. NULL if the field is
   *   datasource-independent.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field's datasource couldn't be loaded.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getDatasource()
   */
  public function getDatasource() {
    if (!isset($this->datasource) && isset($this->datasource_id)) {
      $this->datasource = $this->index->getDatasource($this->datasource_id);
    }
    return $this->datasource;
  }

  /**
   * Retrieves this field's property path.
   *
   * @return string
   *   The property path.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getPropertyPath()
   */
  public function getPropertyPath() {
    return $this->propertyPath;
  }

  /**
   * Retrieves this field's label.
   *
   * The field's label, contrary to the label returned by the field's data
   * definition, contains a human-readable representation of the full property
   * path. The datasource label is not included, though – use getPrefixedLabel()
   * for that.
   *
   * @return string
   *   A human-readable label representing this field's property path.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getLabel()
   */
  public function getLabel() {
    if (!isset($this->label)) {
      $label = '';
      try {
        $label = $this->getDataDefinition()->getLabel();
      }
      catch (SearchApiException $e) {
        watchdog_exception('search_api', $e);
      }
      $pos = strrpos($this->propertyPath, ':');
      if ($pos) {
        $parent_id = substr($this->propertyPath, 0, $pos);
        if ($this->datasource_id) {
          $parent_id = $this->datasource_id . IndexInterface::DATASOURCE_ID_SEPARATOR . $parent_id;
        }
        $label = Utility::createField($this->index, $parent_id)->getLabel() . ' » ' . $label;
      }
      $this->label = $label;
    }
    return $this->label;
  }

  /**
   * Sets this field's label.
   *
   * @param $label
   *   A human-readable label representing this field's property path.
   *
   * @return self
   *   The invoked object.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::setLabel()
   */
  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  /**
   * Retrieves this field's description.
   *
   * @return string|null
   *   A human-readable description for this field, or NULL if the field has no
   *   description.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getDescription()
   */
  public function getDescription() {
    if (!isset($this->description)) {
      try {
        $this->description = $this->getDataDefinition()->getDescription();
      }
      catch (SearchApiException $e) {
        watchdog_exception('search_api', $e);
      }
    }
    return $this->description;
  }

  /**
   * Sets this field's description.
   *
   * @param string|null $description
   *   A human-readable description for this field, or NULL if the field has no
   *   description.
   *
   * @return self
   *   The invoked object.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::setDescription()
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * Retrieves this field's label along with datasource prefix.
   *
   * Returns a value similar to getLabel(), but also contains the datasource
   * label, if applicable.
   *
   * @return string
   *   A human-readable label representing this field's property path and
   *   datasource.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getPrefixedLabel()
   */
  public function getPrefixedLabel() {
    if (!isset($this->labelPrefix)) {
      $this->datasource_id = '';
      if (isset($this->datasource_id)) {
        $this->labelPrefix = $this->datasource_id;
        try {
          $this->labelPrefix = $this->getDatasource()->label();
        }
        catch (SearchApiException $e) {
          watchdog_exception('search_api', $e);
        }
        $this->labelPrefix .= ' » ';
      }
    }
    return $this->labelPrefix . $this->getLabel();
  }

  /**
   * Sets this field's label prefix.
   *
   * @param $label_prefix
   *   A human-readable label representing this field's datasource and ending in
   *   some kind of visual separator.
   *
   * @return self
   *   The invoked object.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::setLabelPrefix()
   */
  public function setLabelPrefix($label_prefix) {
    $this->labelPrefix = $label_prefix;
    return $this;
  }

  /**
   * Retrieves this field's data definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The data definition object for this field.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field's data definition is unknown.
   *
   * @see \Drupal\search_api\Item\GenericFieldInterface::getDataDefinition()
   */
  public function getDataDefinition() {
    if (!isset($this->dataDefinition)) {
      $definitions = $this->index->getPropertyDefinitions($this->datasource_id);
      if (!isset($definitions[$this->fieldIdentifier])) {
        $args['@field'] = $this->fieldIdentifier;
        $args['%index'] = $this->index->label();
        throw new SearchApiException(String::format('Could not retrieve data definition for field "@field" on index %index.', $args));
      }
      $this->dataDefinition = $definitions[$this->fieldIdentifier];
    }
    return $this->dataDefinition;
  }

  /**
   * Implements the magic __sleep() method to control object serialization.
   */
  public function __sleep() {
    $properties = $this->getSerializationProperties();
    return array_keys($properties);
  }

  /**
   * Retrieves the properties that should be serialized.
   *
   * Used in __sleep(), but extracted to be more easily usable for subclasses.
   *
   * @return array
   *   An array mapping property names of this object to their values.
   */
  protected function getSerializationProperties() {
    $this->index_id = $this->index->id();
    $properties = get_object_vars($this);
    // Don't serialize objects in properties.
    unset($properties['index'], $properties['datasource'], $properties['dataDefinition']);
    return $properties;
  }

  /**
   * Implements the magic __wakeup() method to control object unserialization.
   */
  public function __wakeup() {
    if ($this->index_id) {
      $this->index = entity_load('search_api_index', $this->index_id);
      unset($this->index_id);
    }
  }

}
