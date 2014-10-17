<?php

/**
 * @file
 * Contains \Drupal\search_api\Tracker\TrackerInterface.
 */

namespace Drupal\search_api\Tracker;

use Drupal\search_api\Plugin\IndexPluginInterface;

/**
 * Interface which describes a tracker plugin for Search API.
 */
interface TrackerInterface extends IndexPluginInterface {

  /**
   * Tracks items being inserted.
   *
   * @param array $ids
   *   An array of item IDs.
   */
  public function trackItemsInserted(array $ids);

  /**
   * Tracks items being updated.
   *
   * @param array $ids
   *   An array of item IDs.
   */
  public function trackItemsUpdated(array $ids);

  /**
   * Marks all items as updated, or only those of a specific datasource.
   *
   * @param string|null $datasource_id
   *   (optional) If given, only items of that datasource are marked as updated.
   */
  public function trackAllItemsUpdated($datasource_id = NULL);

  /**
   * Tracks items being indexed.
   *
   * @param array $ids
   *   An array of item IDs.
   */
  public function trackItemsIndexed(array $ids);

  /**
   * Tracks items being deleted.
   *
   * @param array|null $ids
   *   An array of item IDs.
   */
  public function trackItemsDeleted(array $ids);

  /**
   * Marks all items as deleted, or only those of a specific datasource.
   *
   * @param string|null $datasource_id
   *   (optional) If given, only items of that datasource are marked as deleted.
   */
  public function trackAllItemsDeleted($datasource_id = NULL);

  /**
   * Retrieves a list of item IDs that need to be indexed.
   *
   * @param int $limit
   *   (optional) The maximum number of items to return. A negative value means
   *   "unlimited".
   * @param string|null $datasource
   *   (optional) If specified, only items of the datasource with that ID are
   *   retrieved.
   *
   * @return array
   *   An array of IDs of items that still need to be indexed.
   */
  public function getRemainingItems($limit = -1, $datasource = NULL);

  /**
   * Retrieves the total number of pending items for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of pending items by.
   *
   * @return int
   *   The total number of pending items.
   */
  public function getRemainingItemsCount($datasource = NULL);

  /**
   * Retrieves the total number of items that are being tracked for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of items by.
   *
   * @return int
   *   The total number of items that are being monitored.
   */
  public function getTotalItemsCount($datasource = NULL);

  /**
   * Retrieves the total number of indexed items for this index.
   *
   * @param string|null $datasource
   *   (optional) The datasource to filter the total number of indexed items by.
   *
   * @return int
   *   The number of items that have been indexed in their latest state for this
   *   index (and datasource, if specified).
   */
  public function getIndexedItemsCount($datasource = NULL);

}
