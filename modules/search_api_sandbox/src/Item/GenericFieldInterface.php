<?php

/**
 * @file
 * Contains \Drupal\search_api\Item\GenericFieldInterface.
 */

namespace Drupal\search_api\Item;

use Drupal\search_api\Index\IndexInterface;

/**
 * Represents any field attached to an index.
 */
interface GenericFieldInterface {

  /**
   * Returns the index of this field.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The index to which this field belongs.
   */
  public function getIndex();

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
   */
  public function setIndex(IndexInterface $index);

  /**
   * Returns the field identifier of this field.
   *
   * @return string
   *   The identifier of this field.
   */
  public function getFieldIdentifier();

  /**
   * Retrieves the ID of this field's datasource.
   *
   * @return string|null
   *   The plugin ID of this field's datasource, or NULL if the field is
   *   datasource-independent.
   */
  public function getDatasourceId();

  /**
   * Returns the datasource of this field.
   *
   * @return \Drupal\search_api\Datasource\DatasourceInterface|null
   *   The datasource to which this field belongs. NULL if the field is
   *   datasource-independent.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field's datasource couldn't be loaded.
   */
  public function getDatasource();

  /**
   * Retrieves this field's property path.
   *
   * @return string
   *   The property path.
   */
  public function getPropertyPath();

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
   */
  public function getLabel();

  /**
   * Sets this field's label.
   *
   * @param $label
   *   A human-readable label representing this field's property path.
   *
   * @return self
   *   The invoked object.
   */
  public function setLabel($label);

  /**
   * Retrieves this field's description.
   *
   * @return string|null
   *   A human-readable description for this field, or NULL if the field has no
   *   description.
   */
  public function getDescription();

  /**
   * Sets this field's description.
   *
   * @param string|null $description
   *   A human-readable description for this field, or NULL if the field has no
   *   description.
   *
   * @return self
   *   The invoked object.
   */
  public function setDescription($description);

  /**
   * Retrieves this field's label along with datasource prefix.
   *
   * Returns a value similar to getLabel(), but also contains the datasource
   * label, if applicable.
   *
   * @return string
   *   A human-readable label representing this field's property path and
   *   datasource.
   */
  public function getPrefixedLabel();

  /**
   * Sets this field's label prefix.
   *
   * @param $label_prefix
   *   A human-readable label representing this field's datasource and ending in
   *   some kind of visual separator.
   *
   * @return self
   *   The invoked object.
   */
  public function setLabelPrefix($label_prefix);

  /**
   * Retrieves this field's data definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The data definition object for this field.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field's data definition is unknown.
   */
  public function getDataDefinition();

}
