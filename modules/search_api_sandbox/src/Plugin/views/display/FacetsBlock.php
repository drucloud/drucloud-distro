<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\display\FacetsBlock.
 */

namespace Drupal\search_api\Plugin\views\display;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\views\Plugin\views\display\Block;
use Drupal\views\Views;

/**
 * Defines a display for viewing a search's facets.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "search_api_facets_block",
 *   title = @Translation("Facets block"),
 *   help = @Translation("Display the search result's facets as a block."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_hook_block = TRUE,
 *   contextual_links_locations = {"block"},
 *   admin = @Translation("Facets block")
 * )
 */
// @todo Needs updating (maybe once Facet API is ported).
// @todo Use $this->getLinkDisplay() instead of/as a default for "linked_path"?
//   Or integrate with getPath()?
class FacetsBlock extends Block {

  /**
   * The cached return value of FacetsBlock::getFieldOptions().
   *
   * @var string[]
   */
  protected $field_options;

  /**
   * {@inheritdoc}
   */
  public function displaysExposed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['linked_path'] = array('default' => '');
    $options['facet_field'] = '';
    $options['hide_block'] = FALSE;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if (!$this->getSearchIndexId()) {
      return;
    }

    switch ($form_state->get('section')) {
      case 'linked_path':
        $form['#title'] .= $this->t('Search page path');
        $form['linked_path'] = array(
          '#type' => 'textfield',
          '#description' => $this->t('The menu path to which search facets will link. Leave empty to use the current path.'),
          '#default_value' => $this->getOption('linked_path'),
        );
        break;
      case 'facet_field':
        $form['facet_field'] = array(
          '#type' => 'select',
          '#title' => $this->t('Facet field'),
          '#options' => $this->getFieldOptions(),
          '#default_value' => $this->getOption('facet_field'),
        );
        break;
      case 'use_more':
        $form['use_more']['#description'] = $this->t('This will add a more link to the bottom of this view, which will link to the base path for the facet links.');
        $form['use_more_always'] = array(
          '#type' => 'value',
          '#value' => $this->getOption('use_more_always'),
        );
        break;
      case 'hide_block':
        $form['hide_block'] = array(
          '#type' => 'checkbox',
          '#title' => $this->t('Hide block'),
          '#description' => $this->t('Hide this block, but still execute the search. Can be used to show native Facet API facet blocks linking to the search page specified above.'),
          '#default_value' => $this->getOption('hide_block'),
        );
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    if (!$this->getSearchIndexId()) {
      $form_state->setError($form, $this->t('The "Facets block" display can only be used with base tables based on search indexes.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $values = $form_state->getValues();
    switch ($form_state->get('section')) {
      case 'linked_path':
        $this->setOption('linked_path', $values['linked_path']);
        break;
      case 'facet_field':
        $this->setOption('facet_field', $values['facet_field']);
        break;
      case 'hide_block':
        $this->setOption('hide_block', $values['hide_block']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $options['linked_path'] = array(
      'category' => 'block',
      'title' => $this->t('Search page path'),
      'value' => $this->getOption('linked_path') ? $this->getOption('linked_path') : $this->t('Use current path'),
    );
    $field_options = $this->getFieldOptions();
    $options['facet_field'] = array(
      'category' => 'block',
      'title' => $this->t('Facet field'),
      'value' => $this->getOption('facet_field') ? $field_options[$this->getOption('facet_field')] : $this->t('None'),
    );
    $options['hide_block'] = array(
      'category' => 'block',
      'title' => $this->t('Hide block'),
      'value' => $this->getOption('hide_block') ? $this->t('Yes') : $this->t('No'),
    );
  }

  /**
   * Retrieves an options list of all available fields.
   *
   * @return string[]
   *   An associative array mapping all indexed fields' identifiers to their
   *   prefixed labels.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If there couldn't be a search index retrieved for the current view.
   */
  protected function getFieldOptions() {
    if (!isset($this->field_options)) {
      $index_id = $this->getSearchIndexId();
      /** @var \Drupal\search_api\Index\IndexInterface $index */
      $index = NULL;
      if (!($index_id && ($index = entity_load('search_api_index', $index_id)))) {
        $table = $this->view->storage->get('base_table');
        $views_data = Views::viewsData($table);
        $table = empty($views_data['table']['base']['title']) ? $table : $views_data['table']['base']['title'];
        throw new SearchApiException(String::format('The "Facets block" display cannot be used with a view for @base_table. Please only use this display with base tables representing Search API indexes.', array('@base_table' => $table)));
      }
      $this->field_options = array();
      foreach ($index->getFields() as $key => $field) {
        $this->field_options[$key] = $field->getPrefixedLabel();
      }
    }
    return $this->field_options;
  }

  /**
   * {@inheritdoc}
   */
  public function renderMoreLink() {
    if ($this->isMoreEnabled()) {
      $path = $this->getOption('linked_path');

      if ($path) {
        $path = $this->view->getUrl(NULL, $path);
        $url_options = array();
        if (!empty($this->view->exposed_raw_input)) {
          $url_options['query'] = $this->view->exposed_raw_input;
        }
        $theme = $this->view->buildThemeFunctions('views_more');
        $path = check_url(_url($path, $url_options));

        return array(
          '#theme' => $theme,
          '#more_url' => $path,
          '#link_text' => String::checkPlain($this->useMoreText()),
          '#view' => $this->view,
        );
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function query(){
    parent::query();

    $query = $this->view->query;
    if (!($query instanceof SearchApiQuery)) {
      return;
    }

    $facet_field = $this->getOption('facet_field');
    if (!$facet_field) {
      return;
    }

    $base_path = $this->getOption('linked_path');
    if (!$base_path) {
      $base_path = $_GET['q'];
    }

    $limit = empty($query->pager->options['items_per_page']) ? 10 : $query->pager->options['items_per_page'];
    $query_options = &$query->getOptions();
    if (!$this->getOption('hide_block')) {
      // If we hide the block, we don't need this extra facet.
      $query_options['search_api_facets']['search_api_views_facets_block'] = array(
        'field' => $facet_field,
        'limit' => $limit,
        'missing' => FALSE,
        'min_count' => 1,
      );
    }
    $query_options['search_api_base_path'] = $base_path;
    $query->range(0, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $query = $this->view->query;

    if (!($query instanceof SearchApiQuery)) {
      return NULL;
    }
    $facet_field = $this->getOption('facet_field');
    if (!$facet_field) {
      return NULL;
    }

    $this->view->execute();

    if ($this->getOption('hide_block')) {
      return NULL;
    }

    $results = $query->getSearchApiResults();

    if (empty($results['search_api_facets']['search_api_views_facets_block'])) {
      return NULL;
    }
    $terms = $results['search_api_facets']['search_api_views_facets_block'];

    $filters = array();
    foreach ($terms as $term) {
      $filter = $term['filter'];
      if ($filter[0] == '"') {
        $filter = substr($filter, 1, -1);
      }
      elseif ($filter != '!') {
        // This is a range filter.
        $filter = substr($filter, 1, -1);
        $pos = strpos($filter, ' ');
        if ($pos !== FALSE) {
          $filter = '[' . substr($filter, 0, $pos) . ' TO ' . substr($filter, $pos + 1) . ']';
        }
      }
      $filters[$term['filter']] = $filter;
    }

    $index = $query->getIndex();
    $options['field'] = $index->options['fields'][$facet_field];
    $options['field']['key'] = $facet_field;
    $options['index id'] = $index->machine_name;
    $options['value callback'] = '_search_api_facetapi_facet_create_label';
    $map = search_api_facetapi_facet_map_callback($filters, $options);

    $facets = array();
    $prefix = rawurlencode($facet_field) . ':';
    foreach ($terms as $term) {
      $name = $filter = $filters[$term['filter']];
      if (isset($map[$filter])) {
        $name = $map[$filter];
      }
      $query['f'][0] = $prefix . $filter;

      // Initializes variables passed to theme hook.
      $variables = array(
        'text' => $name,
        'path' => $query->getOption('search_api_base_path'),
        'count' => $term['count'],
        'options' => array(
          'attributes' => array('class' => 'facetapi-inactive'),
          'html' => FALSE,
          'query' => $query,
        ),
      );

      // Themes the link, adds row to facets.
      $facets[] = array(
        'class' => array('leaf'),
        'data' => theme('facetapi_link_inactive', $variables),
      );
    }

    if (!$facets) {
      return NULL;
    }

    return array(
      'facets' => array(
      '#theme'  => 'item_list',
      '#items'  => $facets,
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $info['content'] = $this->render();
    $info['content']['more'] = $this->renderMoreLink();
    $info['subject'] = Xss::filterAdmin($this->view->getTitle());
    return $info;
  }

  /**
   * Checks whether the current view has a correct base table.
   *
   * @return string|null
   *   If the current view has a Search API base table, the associated search
   *   index's ID. NULL otherwise.
   */
  protected function getSearchIndexId() {
    $table = $this->view->storage->get('base_table');
    if (substr($table, 0, 17) == 'search_api_index_') {
      return substr($table, 17);
    }
    return NULL;
  }

}
