<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\argument\SearchApiTaxonomyTerm.
 */

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Component\Utility\String;

/**
 * Defines a contextual filter searching through all indexed taxonomy fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("search_api_taxonomy_term")
 */
// @todo This seems to be only partially ported to D8.
class SearchApiTaxonomyTerm extends SearchApiArgument {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->fillValue();

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
      $terms = entity_load('taxonomy_term', $this->value);

      if (!empty($terms)) {
        $filter = $this->query->createFilter($outer_conjunction);
        $vocabulary_fields = $this->definition['vocabulary_fields'];
        $vocabulary_fields += array('' => array());
        foreach ($terms as $term) {
          $inner_filter = $filter;
          if ($outer_conjunction != $inner_conjunction) {
            $inner_filter = $this->query->createFilter($inner_conjunction);
          }
          // Set filters for all term reference fields which don't specify a
          // vocabulary, as well as for all fields specifying the term's
          // vocabulary.
          if (!empty($vocabulary_fields[$term->vocabulary_machine_name])) {
            foreach ($vocabulary_fields[$term->vocabulary_machine_name] as $field) {
              $inner_filter->condition($field, $term->tid, $condition_operator);
            }
          }
          foreach ($vocabulary_fields[''] as $field) {
            $inner_filter->condition($field, $term->tid, $condition_operator);
          }
          if ($outer_conjunction != $inner_conjunction) {
            $filter->filter($inner_filter);
          }
        }

        $this->query->filter($filter);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!empty($this->argument)) {
      $this->fillValue();
      $terms = array();
      foreach ($this->value as $tid) {
        $taxonomy_term = taxonomy_term_load($tid);
        if ($taxonomy_term) {
          $terms[] = String::checkPlain($taxonomy_term->name);
        }
      }

      return $terms ? implode(', ', $terms) : String::checkPlain($this->argument);
    }
    else {
      return String::checkPlain($this->argument);
    }
  }

}
