<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\FilterInterface.
 */

namespace Drupal\search_api\Query;

/**
 * Represents a filter on a search query.
 *
 * Filters apply conditions on one or more fields with a specific conjunction
 * (AND or OR) and may contain nested filters.
 */
interface FilterInterface {

  /**
   * Adds a subfilter.
   *
   * @param \Drupal\search_api\Query\FilterInterface $filter
   *   A filter object that should be added as a subfilter.
   *
   * @return $this
   */
  public function filter(FilterInterface $filter);

  /**
   * Adds a new ($field $operator $value) condition.
   *
   * @param string $field
   *   The ID of the field to filter on, e.g. "entity:node|title".
   * @param mixed $value
   *   The value the field should have (or be related to by the operator).
   * @param string $operator
   *   The operator to use for checking the constraint. The following operators
   *   are supported for primitive types: "=", "<>", "<", "<=", ">=", ">". They
   *   have the same semantics as the corresponding SQL operators.
   *   If $field is a fulltext field, $operator can only be "=" or "<>", which
   *   are in this case interpreted as "contains" or "doesn't contain",
   *   respectively.
   *   If $value is NULL, $operator also can only be "=" or "<>", meaning the
   *   field must have no or some value, respectively.
   *
   * @return $this
   */
  public function condition($field, $value, $operator = '=');

  /**
   * Retrieves the conjunction used by this filter.
   *
   * @return string
   *   The conjunction used by this filter – either 'AND' or 'OR'.
   */
  public function getConjunction();

  /**
   * Retrieves all conditions and nested filters contained in this filter.
   *
   * @return array
   *   An array containing this filter's sub-filters. Each of these is either an
   *   array (field, value, operator), or another filter object.
   */
  public function &getFilters();

  /**
   * Checks whether a certain tag was set on this filter.
   *
   * @param string $tag
   *   A tag to check for.
   *
   * @return bool
   *   TRUE if the tag was set for this filter, FALSE otherwise.
   */
  public function hasTag($tag);

  /**
   * Retrieves the tags set on this filter.
   *
   * @return string[]
   *   The tags associated with this filter, as both the array keys and values.
   */
  public function &getTags();

}
