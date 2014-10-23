<?php

/**
 * @file
 * Contains \Drupal\rest_api_doc\EventSubscriber\RoutingSubscriber.
 */

namespace Drupal\rest_api_doc\EventSubscriber;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\State\StateInterface;
use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records route names for rest end-points for later introspection.
 */
class RoutingSubscriber implements EventSubscriberInterface {

  /**
   * Stores the route names for REST end-points.
   *
   * REST endpoints are those defined by the Rest module or Views that contain a
   * REST export display.
   *
   * @var array
   */
  protected $restRouteNames;

  /**
   * Array of built views, keyed by view ID.
   *
   * @var \Drupal\views\ViewExecutable[]
   */
  protected $builtViews;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $viewsStorage;

  /**
   * The view executable factory.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $executableFactory;

  /**
   * The state key-value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new RoutingSubscriber instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key-value store.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\views\ViewExecutableFactory $executable_factory
   *   Views executable factory service.
   */
  public function __construct(StateInterface $state, EntityManagerInterface $entity_manager, ViewExecutableFactory $executable_factory) {
    $this->viewsStorage = $entity_manager->getStorage('view');
    $this->executableFactory = $executable_factory;
    $this->state = $state;
  }

  /**
   * Collects all REST end-points.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function onRouteAlter(RouteBuildEvent $event) {
    $collection = $event->getRouteCollection();
    foreach ($collection->all() as $route_name => $route) {
      // Rest module endpoint.
      if (strpos($route_name, 'rest.') === 0) {
        $this->restRouteNames[$route_name] = $route_name;
      }
      // Views module route.
      if (strpos($route_name, 'view.') === 0 && ($parts = explode('.', $route_name))
        && count($parts) == 3) {
        // We need to introspect the display type.
        list(, $view_id, $display_id) = $parts;
        $entity = $this->viewsStorage->load($view_id);
        if (empty($entity)) {
          // Non-existent view.
          continue;
        }
        // Build the view.
        if (empty($this->builtViews[$view_id])) {
          $this->builtViews[$view_id] = $this->executableFactory->get($entity);
          $view = $this->builtViews[$view_id];
        }
        else {
          $view = $this->builtViews[$view_id];
        }
        // Set the given display.
        $view->setDisplay($display_id);
        $display = $view->getDisplay();
        if ($display instanceof RestExport) {
          $this->restRouteNames[$route_name] = $route_name;
        }
      }
    }
  }

  /**
   * Stores the relevant route-names using the key-value store for later use.
   */
  public function onRouteFinished() {
    $this->state->set('rest_api_doc.rest_route_names', array_keys($this->restRouteNames));
    unset($this->restRouteNames);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    // Try to set a low priority to ensure that all routes are already added.
    $events[RoutingEvents::ALTER][] = array('onRouteAlter', -1024);
    $events[RoutingEvents::FINISHED][] = array('onRouteFinished');
    return $events;
  }
}
