<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\argument\SearchApiDate.
 */

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Component\Utility\String;

/**
 * Defines a contextual filter for conditions on date fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api_date")
 */
class SearchApiDate extends SearchApiArgument {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->fillValue();
    if ($this->value === FALSE) {
      $this->abort();
      return;
    }

    $outer_conjunction = strtoupper($this->operator);

    if (empty($this->options['not'])) {
      $condition_operator = '=';
      $inner_conjunction = 'OR';
    }
    else {
      $condition_operator = '<>';
      $inner_conjunction = 'AND';
    }

    if (!empty($this->value)) {
      $outer_filter = $this->query->createFilter($outer_conjunction);
      // @todo Refactor to use only a single nested filter, and only if
      //   necessary. $value_filter will currently only ever contain a single
      //   child – a condition or a nested filter with two conditions.
      foreach ($this->value as $value) {
        $value_filter = $this->query->createFilter($inner_conjunction);
        $values = explode(';', $value);
        $values = array_map(array($this, 'getTimestamp'), $values);
        if (in_array(FALSE, $values, TRUE)) {
          $this->abort();
          return;
        }
        $is_range = (count($values) > 1);

        $inner_filter = ($is_range ? $this->query->createFilter('AND') : $value_filter);
        $range_op = (empty($this->options['not']) ? '>=' : '<');
        $inner_filter->condition($this->realField, $values[0], $is_range ? $range_op : $condition_operator);
        if ($is_range) {
          $range_op = (empty($this->options['not']) ? '<=' : '>');
          $inner_filter->condition($this->realField, $values[1], $range_op);
          $value_filter->filter($inner_filter);
        }
        $outer_filter->filter($value_filter);
      }

      $this->query->filter($outer_filter);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!empty($this->argument)) {
      $this->fillValue();
      $dates = array();
      foreach ($this->value as $date) {
        $date_parts = explode(';', $date);

        $ts = $this->getTimestamp($date_parts[0]);
        $datestr = format_date($ts, 'short');
        if (count($date_parts) > 1) {
          $ts = $this->getTimestamp($date_parts[1]);
          $datestr .= ' – ' . format_date($ts, 'short');
        }

        if ($datestr) {
          $dates[] = $datestr;
        }
      }
      return $dates ? implode(', ', $dates) : String::checkPlain($this->argument);
    }

    return String::checkPlain($this->argument);
  }

  /**
   * Converts a value to a timestamp, if it isn't one already.
   *
   * @param string|int $value
   *   The value to convert. Either a timestamp, or a date/time string as
   *   recognized by strtotime().
   *
   * @return int|false
   *   The parsed timestamp, or FALSE if an illegal string was passed.
   */
  protected function getTimestamp($value) {
    if (is_numeric($value)) {
      return (int) $value;
    }

    return strtotime($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function unpackArgumentValue() {
    // Set up the defaults.
    if (!isset($this->value)) {
      $this->value = array();
    }
    if (!isset($this->operator)) {
      $this->operator = 'or';
    }

    if (empty($this->argument)) {
      return;
    }

    if (preg_match('/^([-\d;:\s]+\+)*[-\d;:\s]+$/', $this->argument)) {
      // The '+' character in a query string may be parsed as ' '.
      $this->value = explode('+', $this->argument);
    }
    elseif (preg_match('/^([-\d;:\s]+,)*[-\d;:\s]+$/', $this->argument)) {
      $this->operator = 'and';
      $this->value = explode(',', $this->argument);
    }

    // Keep an "error" value if invalid strings were given.
    if (!empty($this->argument) && (empty($this->value) || !is_array($this->value))) {
      $this->value = FALSE;
    }
  }

  /**
   * Aborts the associated query due to an illegal argument.
   *
   * @see \Drupal\search_api\Plugin\views\query\SearchApiQuery::abort()
   */
  protected function abort() {
    $variables['!field'] = $this->definition['group'] . ': ' . $this->definition['title'];
    $this->query->abort(String::format('Illegal argument passed to !field contextual filter.', $variables));
  }

}
