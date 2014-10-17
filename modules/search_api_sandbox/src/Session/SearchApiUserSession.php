<?php

/**
 * @file
 * Contains \Drupal\search_api\Session\SearchApiUserSession.
 */

namespace Drupal\search_api\Session;

use Drupal\Core\Session\AnonymousUserSession;

/**
 * An implementation of the user account interface for indexing purposes.
 */
class SearchApiUserSession extends AnonymousUserSession {

  /**
   * Constructs a SearchApiUserSession object.
   *
   * Intentionally allow only a roles parameter to be passed in, as opposed to
   * AnonymousUserSession which doesn't allow any parameter.
   *
   * @param array $roles
   *   An array of user roles (e.g. 'anonymous', 'authenticated').
   */
  public function __construct(array $roles = array()) {
    parent::__construct();

    if ($roles) {
      $this->roles = $roles;
    }
  }

}
