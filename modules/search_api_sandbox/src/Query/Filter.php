<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\Filter.
 */

namespace Drupal\search_api\Query;

/**
 * Provides a standard implementation for a Search API query filter.
 */
class Filter implements FilterInterface {

  /**
   * Array containing subfilters.
   *
   * Each of these is either an array (field, value, operator), or another
   * \Drupal\search_api\Query\FilterInterface object.
   *
   * @var array
   */
  protected $filters = array();

  /**
   * String specifying this filter's conjunction ('AND' or 'OR').
   *
   * @var string
   */
  protected $conjunction;

  /**
   * An array of tags set on this filter.
   *
   * @var string[]
   */
  protected $tags;

  /**
   * Constructs a Filter object.
   *
   * @param string $conjunction
   *   (optional) The conjunction to use for this filter - either 'AND' or 'OR'.
   * @param string[] $tags
   *   (optional) An arbitrary set of tags. Can be used to identify this filter
   *   after it's been added to the query. This is primarily used by the facet
   *   system to support OR facet queries.
   */
  public function __construct($conjunction = 'AND', array $tags = array()) {
    $this->conjunction = strtoupper(trim($conjunction)) == 'OR' ? 'OR' : 'AND';
    $this->tags = array_combine($tags, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * {@inheritdoc}
   */
  public function filter(FilterInterface $filter) {
    $this->filters[] = $filter;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value, $operator = '=') {
    $this->filters[] = array($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getFilters() {
    return $this->filters;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTag($tag) {
    return isset($this->tags[$tag]);
  }

  /**
   * {@inheritdoc}
   */
  public function &getTags() {
    return $this->tags;
  }

  /**
   * Implements the magic __clone() method to clone nested filters, too.
   */
  public function __clone() {
    foreach ($this->filters as $i => $filter) {
      if (is_object($filter)) {
        $this->filters[$i] = clone $filter;
      }
    }
  }

  /**
   * Implements the magic __toString() method to simplify debugging.
   */
  public function __toString() {
    // Special case for a single, nested filter:
    if (count($this->filters) == 1 && is_object($this->filters[0])) {
      return (string) $this->filters[0];
    }
    $ret = array();
    foreach ($this->filters as $filter) {
      if (is_object($filter)) {
        $ret[] = "[\n  " . str_replace("\n", "\n    ", (string) $filter) . "\n  ]";
      }
      else {
        $ret[] = "$filter[0] $filter[2] " . str_replace("\n", "\n    ", var_export($filter[1], TRUE));
      }
    }
    return $ret ? '  ' . implode("\n{$this->conjunction}\n  ", $ret) : '';
  }

}
