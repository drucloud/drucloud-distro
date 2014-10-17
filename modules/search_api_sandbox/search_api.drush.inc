<?php

/**
 * @file
 * Drush commands for Search API.
 */

use Drupal\search_api\Entity\Index;

/**
 * Implements hook_drush_command().
 */
function search_api_drush_command() {
  $items = array();

  $items['search-api-list'] = array(
    'description' => 'List all search indexes.',
    'examples' => array(
      'drush search-api-list' => dt('List all search indexes.'),
      'drush sapi-l' => dt('Alias to list all search indexes.'),
    ),
    'aliases' => array('sapi-l'),
  );

  $items['search-api-enable'] = array(
    'description' => 'Enable one or more disabled search indexes.',
    'examples' => array(
      'drush search-api-enable node_index' => dt('Enable the search index with the ID !name.', array('!name' => 'node_index')),
      'drush sapi-en node_index' => dt('Alias to enable the search index with the ID !name.', array('!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index to enable.'),
    ),
    'aliases' => array('sapi-en'),
  );

  $items['search-api-enable-all'] = array(
    'description' => 'Enable all disabled search indexes.',
    'examples' => array(
      'drush search-api-enable-all' => dt('Enable all disabled indexes.'),
      'drush sapi-ena' => dt('Alias to enable all disabled indexes.'),
    ),
    'arguments' => array(),
    'aliases' => array('sapi-ena'),
  );

  $items['search-api-disable'] = array(
    'description' => 'Disable one or more enabled search indexes.',
    'examples' => array(
      'drush search-api-disable node_index' => dt('Disable the search index with the ID !name.', array('!name' => 'node_index')),
      'drush sapi-dis node_index' => dt('Alias to disable the search index with the ID !name.', array('!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index to disable.'),
    ),
    'aliases' => array('sapi-dis'),
  );

  $items['search-api-disable-all'] = array(
    'description' => 'Disable all enabled search indexes.',
    'examples' => array(
      'drush search-api-disable-all' => dt('Disable all enabled indexes.'),
      'drush sapi-disa' => dt('Alias to disable all enabled indexes.'),
    ),
    'arguments' => array(),
    'aliases' => array('sapi-disa'),
  );

  $items['search-api-status'] = array(
    'description' => 'Show the status of one or all search indexes.',
    'examples' => array(
      'drush search-api-status' => dt('Show the status of all search indexes.'),
      'drush sapi-s' => dt('Alias to show the status of all search indexes.'),
      'drush sapi-s node_index' => dt('Show the status of the search index with the ID !name.', array('!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index to view.'),
    ),
    'aliases' => array('sapi-s'),
  );

  $items['search-api-index'] = array(
    'description' => 'Index items for one or all enabled search indexes.',
    'examples' => array(
      'drush search-api-index' => dt('Index all items for all enabled indexes.'),
      'drush sapi-i' => dt('Alias to index all items for all enabled indexes.'),
      'drush sapi-i node_index' => dt('Index all items for the index with the ID !name.', array('!name' => 'node_index')),
      'drush sapi-i node_index 100' => dt('Index a maximum number of !limit items for the index with the ID !name.', array('!limit' => 100, '!name' => 'node_index')),
      'drush sapi-i node_index 100 10' => dt('Index a maximum number of !limit items (!batch_size items per batch run) for the index with the ID !name.', array('!limit' => 100, '!batch_size' => 10, '!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index.'),
      'limit' => dt('The number of items to index. Set to 0 to index all items. Defaults to 0 (index all).'),
      'batch_size' => dt('The number of items to index per batch run. Set to 0 to index all items at once. Defaults to the "!batch_size" setting of the index.', array('!batch_size' => dt('Cron batch size'))),
    ),
    'aliases' => array('sapi-i'),
  );

  $items['search-api-reindex'] = array(
    'description' => 'Force reindexing of one or all search indexes, without deleting existing index data.',
    'examples' => array(
      'drush search-api-reindex' => dt('Schedule all search indexes for reindexing.'),
      'drush sapi-r' => dt('Alias to schedule all search indexes for reindexing .'),
      'drush sapi-r node_index' => dt('Schedule the search index with the ID !name for reindexing.', array('!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index.'),
    ),
    'aliases' => array('sapi-r'),
  );

  $items['search-api-clear'] = array(
    'description' => 'Clear one or all search indexes and mark them for reindexing.',
    'examples' => array(
      'drush search-api-clear' => dt('Clear all search indexes.'),
      'drush sapi-c' => dt('Alias to clear all search indexes.'),
      'drush sapi-c node_index' => dt('Clear the search index with the ID !name.', array('!name' => 'node_index')),
    ),
    'arguments' => array(
      'index_id' => dt('The ID of an index.'),
    ),
    'aliases' => array('sapi-c'),
  );

  return $items;
}

/**
 * Prints a list of all search indexes.
 */
function drush_search_api_list() {
  $indexes = search_api_drush_get_indexes();
  if (!$indexes) {
    return;
  }

  $rows[] = array(
    dt('Machine name'),
    dt('Name'),
    dt('Server'),
    dt('Type'),
    dt('Status'),
    dt('Limit'),
  );
  $none = '(' . dt('none') . ')';
  $enabled = dt('enabled');
  $disabled = dt('disabled');

  foreach ($indexes as $index) {
    $types = array();
    foreach ($index->getDatasources() as $datasource) {
      $types[] = $datasource->label();
    }
    $rows[] = array(
      $index->id(),
      $index->label(),
      $index->getServerId() ? $index->getServer()->label() : $none,
      $types ? implode(', ', $types) : $none,
      $index->status() ? $enabled : $disabled,
      (int) $index->getOption('cron_limit'),
    );
  }

  drush_print_table($rows);
}

/**
 * Enables one or more search indexes.
 *
 * @param string|null …
 *   The ID of a search index to be enabled. Or NULL (only used internally) to
 *   enable all disabled indexes.
 */
function drush_search_api_enable() {
  if (!search_api_drush_get_index_count()) {
    drush_set_error(dt('There are no indexes defined. Please define an index before trying to enable it.'));
    return;
  }

  $index_ids = func_get_args();

  if (!$index_ids) {
    drush_set_error(dt('You must specify at least one index to enable. The indexes I know about are:'));
    drush_search_api_list();
  }

  foreach ($index_ids as $index_id) {
    $indexes = search_api_drush_get_indexes($index_id);
    if (!$indexes) {
      continue;
    }
    foreach ($indexes as $index) {
      if ($index_id || !$index->status()) {
        search_api_drush_set_index_state($index);
      }
    }
  }
}

/**
 * Enables all search indexes.
 */
function drush_search_api_enable_all() {
  drush_search_api_enable(NULL);
}

/**
 * Disables one or more search indexes.
 *
 * @param string|null …
 *   The ID of a search index to be disabled. Or NULL (only used internally) to
 *   disable all disabled indexes.
 */
function drush_search_api_disable() {
  if (!search_api_drush_get_index_count()) {
    drush_set_error(dt('There are no indexes defined. Please define an index before trying to disable it.'));
    return;
  }

  $index_ids = func_get_args();

  if (!$index_ids) {
    drush_set_error(dt('You must specify at least one index to disable. The indexes I know about are:'));
    drush_search_api_list();
  }

  foreach ($index_ids as $index_id) {
    $indexes = search_api_drush_get_indexes($index_id);
    if (!$indexes) {
      continue;
    }
    foreach ($indexes as $index_name => $index) {
      if ($index->status()) {
        search_api_drush_set_index_state($index, FALSE);
      }
    }
  }
}

/**
 * Disables all search indexes.
 */
function drush_search_api_disable_all() {
  drush_search_api_disable(NULL);
}

/**
 * Displays the status of one or all search indexes.
 *
 * @param string|null $index_id
 *   (optional) The ID of the search index whose status should be displayed, or
 *   NULL to display the status of all search indexes.
 */
function drush_search_api_status($index_id = NULL) {
  $indexes = search_api_drush_get_indexes($index_id);
  if (!$indexes) {
    return;
  }

  $rows[] = array(
    dt('Machine name'),
    dt('Name'),
    dt('% Complete'),
    dt('Indexed'),
    dt('Total'),
  );

  foreach ($indexes as $index) {
    $indexed = $index->getTracker()->getIndexedItemsCount();
    $total = $index->getTracker()->getTotalItemsCount();

    $complete = '-';
    if ($total > 0) {
      $complete = (100 * round($indexed / $total, 3)) . '%';
    }

    $row = array(
      $index->id(),
      $index->label(),
      $complete,
      $indexed,
      $total,
    );
    $rows[] = $row;
  }

  drush_print_table($rows);
}

/**
 * Indexes items.
 *
 * @param string|null $index_id
 *   (optional) The index ID for which items should be indexed, or NULL to index
 *   items on all indexes.
 * @param int|null $limit
 *   (optional) Maximum number of items to index, or NULL to index all items.
 * @param int|null $batch_size
 *   (optional) Number of items to index per batch, or NULL to use the index's
 *   "cron_limit" setting.
 *
 * @todo Currently, $batch_size is ignored and all items indexed at once.
 */
function drush_search_api_index($index_id = NULL, $limit = NULL, $batch_size = NULL) {
  $indexes = search_api_drush_get_indexes($index_id);
  if (!$indexes) {
    return;
  }

  foreach ($indexes as $index) {
    $tracker = $index->getTracker();
    $remaining = $tracker->getTotalItemsCount() - $tracker->getIndexedItemsCount();

    if (!$remaining) {
      drush_log(dt("The index !index is up to date.", array('!index' => $index->label())), 'ok');
      continue;
    }
    else {
      $args = array(
        '!remaining' => $remaining,
        '!limit' => $limit ? $limit : dt('all'),
        '!index' => $index->label(),
      );
      drush_log(dt("Found !remaining items to index for !index. Indexing !limit items.", $args), 'ok');
    }

    $index->index($limit);
    // @todo Report number of successfully indexed items, and check whether that
    //   matched the expectations.

    // Get the number of items to index per batch run.
    /*if (!isset($batch_size)) {
      $cron_limit = $index->getOption('cron_limit');
      $searchApiSettings = \Drupal::configFactory()->get('search_api.settings');
      $batch_size = empty($cron_limit) ?: $searchApiSettings->get('cron_limit');
    }
    elseif ($batch_size <= 0) {
      $batch_size = $remaining;
    }

    // Get the number items to index.
    if (!isset($limit) || !is_int($limit += 0) || $limit <= 0) {
      $limit = $remaining;
    }

    drush_log(dt("Indexing a maximum number of !limit items (!batch_size items per batch run) for the index !index.", array('!index' => $index->name, '!limit' => $limit, '!batch_size' => $batch_size)), 'ok');

    drush_log("@todo: INITIATING BATCH IS NOT YET IMPLEMENTED.", 'error');
    return;

    // Create the batch.
    if (!_search_api_batch_indexing_create($index, $batch_size, $limit, $remaining, TRUE)) {
      drush_log(dt("Couldn't create a batch, please check the batch size and limit parameters."), 'error');
    }
    else {
      // Launch the batch process.
      drush_backend_batch_process();
    }*/
  }
}

/**
 * Schedules a search index for reindexing.
 *
 * @param string|null $index_id
 *   (optional) The index ID for which items should be reindexed, or NULL to
 *   reindex all search indexes.
 */
function drush_search_api_reindex($index_id = NULL) {
  $indexes = search_api_drush_get_indexes($index_id);
  if (!$indexes) {
    return;
  }
  foreach ($indexes as $index) {
    if ($index->status()) {
      $index->reindex();
      drush_log(dt('!index was successfully marked for reindexing.', array('!index' => $index->label())), 'ok');
    }
  }
}

/**
 * Clears a search index.
 *
 * @param string|null $index_id
 *   (optional) The ID of the search index which should be cleared, or NULL to
 *   clear all search indexes.
 */
function drush_search_api_clear($index_id = NULL) {
  $indexes = search_api_drush_get_indexes($index_id);
  if (!$indexes) {
    return;
  }
  foreach ($indexes as $index) {
    if ($index->status()) {
      $index->clear();
      drush_log(dt('!index was successfully cleared.', array('!index' => $index->label())), 'ok');
    }
  }
}

/**
 * Returns the total number of search indexes.
 *
 * @return int
 *   The number of search indexes on this site.
 */
function search_api_drush_get_index_count() {
  return count(Index::loadMultiple());
}

/**
 * Returns either one or all indexes as an array.
 *
 * If the index is not defined or there are no indexes then an error message
 * is displayed
 *
 * @param string|null $index_id
 *   (optional) Either the ID of the search index to load, or NULL to load all
 *   indexes.
 *
 * @return \Drupal\search_api\Index\IndexInterface[]
 *   An array of search indexes.
 */
function search_api_drush_get_indexes($index_id = NULL) {
  if (!isset($index_id)) {
    $indexes = Index::loadMultiple();
  }
  else {
    $indexes = Index::load($index_id);
    $indexes = $indexes ? array($index_id => $indexes) : array();
  }

  // If we found no indexes, display an error message.
  if (!$indexes) {
    if (!isset($index_id)) {
      drush_set_error(dt('No indexes present.'));
    }
    else {
      drush_set_error(dt('Invalid index ID "!index_id". The following indexes are defined:', array('!index_id' => $index_id)));
      drush_print();
      drush_search_api_list();
    }
  }

  return $indexes;
}

/**
 * Changes the state of a single index.
 *
 * Displays helpful messages if the state cannot be changed.
 *
 * @param \Drupal\search_api\Index\IndexInterface $index
 *   The index to be enabled.
 * @param boolean $enable
 *   (optional) TRUE to enable, FALSE to disable the index.
 */
function search_api_drush_set_index_state($index, $enable = TRUE) {
  $state_label = $enable ? dt('enabled') : dt('disabled');
  $method = $enable ? 'enable' : 'disable';

  if ($index->status() == $enable) {
    drush_log(dt("The index !index is already !desired_state.", array('!index' => $index->label(), '!desired_state' => $state_label)), 'ok');
    return;
  }
  if (!$index->getServerId()) {
    drush_log(dt("Index !index could not be !desired_state because it is not bound to any server.", array('!index' => $index->label(), '!desired_state' => $state_label)), 'warning');
    return;
  }

  $index->$method()->save();
  drush_log(dt("The index !index was successfully !desired_state.", array('!index' => $index->label(), '!desired_state' => $state_label)), 'ok');
}
