<?php

/**
 * @file
 * Defines theme functions for the Search API module.
 */

use Drupal\Component\Utility\String;
use Drupal\Core\Render\Element;
use Drupal\search_api\Utility\Utility;

/**
 * Returns HTML for a fields form table.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: A render element representing the form.
 *
 * @return string
 *   The rendered HTML for a fields form table.
 *
 * @ingroup themeable
 */
function theme_search_api_admin_fields_table($variables) {
  $form = $variables['element'];
  $header = array(t('Field'), t('Machine name'), t('Indexed'), t('Type'), t('Boost'));

  $rows = array();
  if (!empty($form['fields'])) {
    foreach (Element::children($form['fields']) as $name) {
      $row = array();
      foreach (Element::children($form['fields'][$name]) as $field) {
        if ($cell = render($form['fields'][$name][$field])) {
          $row[] = $cell;
        }
      }
      if (empty($form['fields'][$name]['description']['#value'])) {
        $rows[] = Utility::deepCopy($row);
      }
      else {
        $rows[] = array(
          'data' => $row,
          'title' => strip_tags($form['fields'][$name]['description']['#value']),
        );
      }
    }
  }

  $note = isset($form['note']) ? $form['note'] : '';
  $additional = isset($form['additional']) ? $form['additional'] : FALSE;
  unset($form['note'], $form['submit'], $form['additional']);
  $output = drupal_render_children($form);

  $build = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );

  $output .= drupal_render($build);
  $output .= render($note);
  if ($additional) {
    $output .= render($additional);
  }

  return $output;
}

/**
 * Returns HTML for a search server.
 *
 * @param array $variables
 *   An associative array containing:
 *   - server: The server that should be displayed.
 *
 * @return string
 *   The rendered HTML for a search server.
 *
 * @ingroup themeable
 */
function theme_search_api_server($variables) {
  // Get the search server.
  /** @var $server \Drupal\search_api\Server\ServerInterface */
  $server = $variables['server'];

  $output = '';

  if (($description = $server->getDescription())) {
    // Sanitize the description and append to the output.
    $output .= '<p class="description">' . nl2br(String::checkPlain($description)) . '</p>';
  }

  // Initialize the $rows variable which will hold the different parts of server
  // information.
  $rows = array();
  // Create a row template with references so we don't have to deal with the
  // complicated structure for each individual row.
  $row = array(
    'data' => array(
      array('header' => TRUE),
      '',
    ),
    'class' => array(''),
  );
  // Get the individual parts of the row by reference.
  $label = &$row['data'][0]['data'];
  $info = &$row['data'][1];
  $class = &$row['class'][0];

  // Check if the server is enabled.
  if ($server->status()) {
    $class = 'ok';
    $info = t('enabled (<a href="@url">disable</a>)', array('@url' => $server->urlInfo('disable')->toString()));
  }
  else {
    $class = 'warning';
    $info = t('disabled (<a href="@url">enable</a>)', array('@url' => $server->urlInfo('enable')->toString()));
  }
  // Append the row and reset variables.
  $label = t('Status');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if the backend used by the server is valid and get its label.
  if ($server->hasValidBackend()) {
    $backend_plugin_definition = $server->getBackend()->getPluginDefinition();
    $info = String::checkPlain($backend_plugin_definition['label']);
  }
  else {
    $class = 'error';
    $info = t('Invalid or missing backend plugin: %backend_id', array('%backend_id' => $server->getBackendId()));
  }
  // Append the row and reset variables.
  $label = t('Backend class');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Build the indexes links container.
  $indexes = array(
    '#theme' => 'links',
    '#attributes' => array('class' => array('inline')),
    '#links' => array(),
  );
  // Add links for all indexes attached to this server.
  foreach ($server->getIndexes() as $index) {
    $indexes['#links'][] = array(
      'title' => $index->label(),
      'href' => $index->getSystemPath(),
    );
  }
  // Check if the indexes variable contains links.
  if ($indexes['#links']) {
    $label = t('Search indexes');
    $info = render($indexes);
    $rows[] = Utility::deepCopy($row);
  }

  // Add backend-specific additional information.
  foreach ($server->viewSettings() as $information) {
    // Convert the extra information and append the information to the row.
    $label = $information['label'];
    $info = $information['info'];
    $class = (empty($information['status']) ? '' : $information['status']);
    $rows[] = Utility::deepCopy($row);
  }

  // Append the server info table to the output.
  $server_info_table = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#attributes' => array(
      'class' => array(
        'search-api-server-summary',
      ),
    ),
  );
  $output .= drupal_render($server_info_table);

  return $output;
}

/**
 * Returns HTML for a search index.
 *
 * @param array $variables
 *   An associative array containing:
 *     - index: The search index to display.
 *
 * @return string
 *   The rendered HTML for a search index.
 *
 * @ingroup themeable
 */
// @todo This executes a search in the theme layer, amongst other things.
//   Definitely not cool.
function theme_search_api_index($variables) {
  // Get the index.
  /** @var $index \Drupal\search_api\Index\IndexInterface */
  $index = $variables['index'];
  $server = $index->hasValidServer() ? $index->getServer() : NULL;
  $tracker = $index->hasValidTracker() ? $index->getTracker() : NULL;

  $output = '';

  if (($description = $index->getDescription())) {
    // Sanitize the description and append to the output.
    $output .= '<p class="description">' . nl2br(String::checkPlain($description)) . '</p>';
  }

  // Initialize the $rows variable which will hold the different parts of server
  // information.
  $rows = array();
  // Create a row template with references so we don't have to deal with the
  // complicated structure for each individual row.
  $row = array(
    'data' => array(
      array('header' => TRUE),
      '',
    ),
    'class' => array(''),
  );
  // Get the individual parts of the row by reference.
  $label = &$row['data'][0]['data'];
  $info = &$row['data'][1];
  $class = &$row['class'][0];

  // Check if the index is enabled.
  if ($index->status()) {
    $class = 'ok';
    $info = t('enabled (<a href="@url">disable</a>)', array('@url' => $index->urlInfo('disable')->toString()));
  }
  // Check if a server is available and enabled.
  elseif ($server && $server->status()) {
    $class = 'warning';
    $info = t('disabled (<a href="@url">enable</a>)', array('@url' => $index->urlInfo('enable')->toString()));
  }
  else {
    $class = 'warning';
    $info = t('disabled');
  }
  // Append the row and reset variables.
  $label = t('Status');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  foreach ($index->getDatasourceIds() as $datasource_id) {
    // Check if the datasource is valid.
    if ($index->isValidDatasource($datasource_id)) {
      $info = $index->getDatasource($datasource_id)->getPluginDefinition()['label'];
    }
    else {
      $class = 'error';
      $info = t('Invalid or missing datasource plugin: %datasource_id', array('%datasource_id' => $datasource_id));
    }
    // Append the row and reset variables.
    $label = t('Data sources');
    $rows[] = Utility::deepCopy($row);
    $class = '';
  }

  // Check if the tracker is valid.
  if ($tracker) {
    $info = $tracker->getPluginDefinition()['label'];
  }
  else {
    $class = 'error';
    $info = t('Invalid or missing tracker plugin: %tracker_id', array('%tracker_id' => $index->getTrackerId()));
  }
  // Append the row and reset variables.
  $label = t('Tracker');
  $rows[] = Utility::deepCopy($row);
  $class = '';

  // Check if a server is available.
  if ($server) {
    $label = t('Server');
    $info = \Drupal::linkGenerator()->generate($server->label(), $server->urlInfo('canonical'));
    $rows[] = Utility::deepCopy($row);
  }
  elseif ($index->getServerId()) {
    $class = 'error';
    $label = t('Server');
    $info = t('Unknown server set for index: %server_id', array('%server_id' => $index->getServerId()));
    $rows[] = Utility::deepCopy($row);
    $class = '';
  }

  // Check if the index is enabled.
  if ($index->status()) {
    // Check if a valid server is available.
    if ($server) {
      // Here we want to get a raw count of the total number of items on the
      // server for this index. Therefore we circumvent the whole preprocessing
      // (which could add facets, access filters, etc.) and hand the query
      // directly to the server.
      // @todo Add a cleaner way for doing this (i.e., skipping preprocessing).
      $query = $index->query();
      $query->fields(array())->range(0, 0);
      $response = $server->search($query);

      // Build the server index status translation variables.
      $vars = array('@url' => _url('https://drupal.org/node/2009804#server-index-status'));
      // Build the server index status info.
      $label = t('Server index status');
      $info = \Drupal::translation()->formatPlural($response->getResultCount(), 'There is 1 item indexed on the server for this index. (<a href="@url">More information</a>)', 'There are @count items indexed on the server for this index. (<a href="@url">More information</a>)', $vars);
      $rows[] = Utility::deepCopy($row);
    }

    $cron_limit = $index->getOption('cron_limit', \Drupal::configFactory()->get('search_api.settings')->get('cron_limit'));
    // Check if the cron limit is higher than zero.
    if ($cron_limit > 0) {
      $class = 'ok';
      $info = \Drupal::translation()->formatPlural($cron_limit, 'During cron runs, 1 item will be indexed per batch.', 'During cron runs, @count items will be indexed per batch.');
    }
    else {
      $class = 'warning';
      $info = t('No items will be indexed during cron runs.');
    }
    // Append the row and reset variables.
    $label = t('Cron batch size');
    $rows[] = Utility::deepCopy($row);
    $class = '';

    // Add the indexing progress bar.
    if ($tracker) {
      $indexed_count = $tracker->getIndexedItemsCount();
      $total_count = $tracker->getTotalItemsCount();
      $percent = ($total_count !== 0) ? (100 * $indexed_count / $total_count) : 0;

      $index_progress = array(
        '#theme' => 'progress_bar',
        '#percent' => $percent,
        '#message' => t('@indexed/@total indexed', array('@indexed' => $indexed_count, '@total' => $total_count)),
      );
      $output .= '<h3>' . t('Index status') . '</h3>';
      $output .= '<div class="search-api-index-status">' . drupal_render($index_progress) . '</div>';
    }
  }

  // Append the index info table to the output.
  $index_info_table = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#attributes' => array(
      'class' => array(
        'search-api-index-summary',
      ),
    ),
  );
  $output .= drupal_render($index_info_table);

  return $output;
}
