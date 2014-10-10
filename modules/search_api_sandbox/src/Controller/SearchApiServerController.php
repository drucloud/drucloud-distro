<?php

/**
* @file
* Contains \Drupal\search_api\Controller\SearchApiServerController.
*/

namespace Drupal\search_api\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\Server\ServerInterface;

/**
 * Provides block routines for search server-specific routes.
 */
class SearchApiServerController extends ControllerBase {

  /**
   * Displays information about a search server.
   *
   * @param \Drupal\search_api\Server\ServerInterface $search_api_server
   *   The server to display.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(ServerInterface $search_api_server) {
    // Build the search server information.
    $render = array(
      'view' => array(
        '#theme' => 'search_api_server',
        '#server' => $search_api_server,
      ),
      '#attached' => array(
        'css' => array(
          drupal_get_path('module', 'search_api') . '/css/search_api.admin.css'
        ),
      ),
    );
    // Check if the server is enabled.
    if ($search_api_server->status()) {
      // Attach the server status form.
      $render['form'] = $this->formBuilder()->getForm('Drupal\search_api\Form\ServerStatusForm', $search_api_server);
    }
    return $render;
  }

  /**
   * Page title callback for a server's "View" tab.
   *
   * @param \Drupal\search_api\Server\ServerInterface $search_api_server
   *   The server that is displayed.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(ServerInterface $search_api_server) {
    return String::checkPlain($search_api_server->label());
  }

  /**
   * Enables a search server without a confirmation form.
   *
   * @param \Drupal\search_api\Server\ServerInterface $search_api_server
   *   The server to be enabled.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to send to the browser.
   */
  public function serverBypassEnable(ServerInterface $search_api_server) {
    $search_api_server->setStatus(TRUE)->save();

    // Notify the user about the status change.
    drupal_set_message($this->t('The search server %name has been enabled.', array('%name' => $search_api_server->label())));

    // Redirect to the server's "View" page.
    $url = $search_api_server->urlInfo();
    return $this->redirect($url->getRouteName(), $url->getRouteParameters());
  }

}
