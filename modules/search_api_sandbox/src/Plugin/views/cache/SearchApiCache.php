<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\views\cache\SearchApiCache.
 */

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\search_api\Exception\SearchApiException;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\views\Plugin\views\cache\Time;

/**
 * Defines a cache plugin for use with Search API views.
 *
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "search_api",
 *   title = @Translation("Search API specifc"),
 *   help = @Translation("Cache Search API views. (Other methods probably won't work with search views.)")
 * )
 */
// @todo Limit to Search API base tables.
class SearchApiCache extends Time {

  /**
   * Static cache for SearchApiCache::getResultsKey().
   *
   * @var string|null
   */
  protected $resultsKey;

  /**
   * {@inheritdoc}
   */
  public function cacheSet($type) {
    if ($type != 'results') {
      parent::cacheSet($type);
      return;
    }

    $data = array(
      'result' => $this->view->result,
      'total_rows' => isset($this->view->total_rows) ? $this->view->total_rows : 0,
      'current_page' => $this->view->getCurrentPage(),
      'search_api results' => $this->getQuery()->getSearchApiResults(),
    );
    \Drupal::cache($this->resultsBin)->set($this->generateResultsKey(), $data, $this->cacheSetExpire($type), $this->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGet($type) {
    if ($type != 'results') {
      return parent::cacheGet($type);
    }

    // Values to set: $view->result, $view->total_rows, $view->execute_time,
    // $view->current_page.
    if ($cache = \Drupal::cache($this->resultsBin)->get($this->generateResultsKey())) {
      $cutoff = $this->cacheExpire($type);
      if (!$cutoff || $cache->created > $cutoff) {
        $this->view->result = $cache->data['result'];
        $this->view->total_rows = $cache->data['total_rows'];
        $this->view->setCurrentPage($cache->data['current_page']);
        $this->view->execute_time = 0;

        // Trick Search API into believing a search happened, to make facetting
        // et al. work.
        $query = $this->getQuery()->getSearchApiQuery();
        // @todo Find a replacement for that. Also set it on the Views Query?
        search_api_current_search($query->getOption('search id'), $query, $cache->data['search_api results']);

        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateResultsKey() {
    if (!isset($this->resultsKey)) {
      $query = $this->getQuery()->getSearchApiQuery();
      $query->preExecute();
      $user = \Drupal::currentUser();
      $key_data = array(
        'query' => $query,
        'roles' => $user->getRoles(),
        'super-user' => $user->id() == 1, // special caching for super user.
        'langcode' => \Drupal::languageManager()->getCurrentLanguage()->id,
        'base_url' => $GLOBALS['base_url'],
      );
      foreach (array('exposed_info', 'page', 'sort', 'order', 'items_per_page', 'offset') as $key) {
        if ($this->view->getRequest()->query->has($key)) {
          $key_data[$key] = $this->view->getRequest()->query->get($key);
        }
      }

      $this->resultsKey = $this->view->storage->id() . ':' . $this->displayHandler->display['id'] . ':results:' . hash('sha256', serialize($key_data));
    }

    return $this->resultsKey;
  }

  /**
   * Retrieves the Search API Views query for the current view.
   *
   * @return \Drupal\search_api\Plugin\views\query\SearchApiQuery|null
   *   The Search API Views query associated with the current view.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If there is no current Views query, or it is no Search API query.
   */
  protected function getQuery() {
    if (isset($this->view->query) && $this->view->query instanceof SearchApiQuery) {
      return $this->view->query;
    }
    throw new SearchApiException('No matching Search API Views query found in view.');
  }

}
