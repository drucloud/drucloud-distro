<?php

/**
 * @file
 * Contains \Drupal\rest_api_doc\Controller\RestApiDocController.
 */

namespace Drupal\rest_api_doc\Controller;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Url;
use Drupal\simpletest\TestDiscovery;
use Drupal\user\PermissionHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines controller for handling rest_api_doc routes.
 */
class RestApiDocController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The state key-value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The rest_api_doc settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Array of permissions returned from hook_permission.
   *
   * @var array
   */
  protected $permissions;

  /**
   * Permission handler service.
   *
   * @var \Drupal\user\PermissionHandler
   */
  protected $permissionHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('state'),
      $container->get('config.factory')->get('rest_api_doc.settings'),
      $container->get('token'),
      $container->get('user.permissions')
    );
  }

  /**
   * Constructs a new RestApiDocController.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\Config $config
   *   The rest_api_doc settings config object.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\user\PermissionHandler $permission_handler
   *   Permission handler service.
   */
  public function __construct(RouteProviderInterface $route_provider, StateInterface $state, Config $config, Token $token, PermissionHandler $permission_handler) {
    $this->routeProvider = $route_provider;
    $this->state = $state;
    $this->config = $config;
    $this->token = $token;
    $this->permissionHandler = $permission_handler;
  }

  /**
   * Controller callback for API overview.
   */
  public function summary() {
    $route_names = $this->config->get('routes');
    if (empty($route_names)) {
      $return = array(
        '#markup' => $this->t('No REST API endpoints configured or available.'),
      );
      if ($this->currentUser()->hasPermission('administer rest_api_doc')) {
        $return['#markup'] .= ' ' . $this->t('Please !link routes used for REST API documentation.', array(
          '!link' => $this->l($this->t('configure'), Url::fromRoute('rest_api_doc.settings')),
        ));;
      }
      return $return;
    }
    $overview = $this->token->replace(Xss::filterAdmin($this->config->get('overview')));
    $return['overview'] = array(
      '#markup' => $overview,
    );

    $links = array();
    $routes = $this->routeProvider->getRoutesByNames($route_names);
    foreach ($routes as $route_name => $route) {
      $path = $route->getPath();
      $links[$path] = $this->l($path, Url::fromRoute('rest_api_doc.documentation_detail', array(
        'path' => str_replace('/', '::', $path),
      )));
    }

    $return['toc'] = array(
      '#title' => $this->t('Available end-points'),
      '#theme' => 'item_list',
      '#items' => $links,
    );
    return $return;
  }

  /**
   * Controller callback for route detail.
   */
  public function routeDetail($path) {
    $path = str_replace('::', '/', $path);
    $theme_key = str_replace(array('/', '{', '}'), array('__', '', ''), $path);
    $routes = $this->routeProvider->getRoutesByPattern($path)->all();
    $return = array(
      'overview' => array(
        '#theme' => 'rest_api_doc_detail__' . $theme_key,
        '#path' => $path,
        '#title' => $path,
      ),
    );
    $first = TRUE;
    foreach ($routes as $route_name => $route) {
      if (!in_array($route_name, $this->config->get('routes'))) {
        continue;
      }
      $return[$route_name] = array(
        '#type' => 'details',
        '#title' => $path . ' (' . implode(', ', $route->getMethods()) . ' )',
        '#open' => $first,
      );
      $first = FALSE;
      $controller = $route->getDefault('_controller');
      if (strpos($controller, '::') !== FALSE) {
        list($controller_class, $controller_method) = explode('::', $controller);
        $reflection = new \ReflectionMethod($controller_class, $controller_method);
        if ($php_doc = $this->parseMethodDocBlock($reflection)) {
          $return[$route_name]['summary'] = array(
            '#markup' => Xss::filterAdmin($php_doc),
          );
        }
      }
      $auth = array();
      if ($route->getRequirement('_access_rest_csrf')) {
        $auth['CSRF token'] = $this->t('CSRF token: REQUIRED');
      }
      if ($authentication_methods = $route->getOption('_auth')) {
        $auth += $authentication_methods;
      }
      $return[$route_name]['auth'] = array(
        '#theme' => 'item_list',
        '#items' => $auth,
        '#title' => $this->t('Authentication methods'),
      );
      if ($formats = $route->getRequirement('_format')) {
        $return[$route_name]['formats'] = array(
          '#theme' => 'item_list',
          '#items' => explode('|', $formats),
          '#title' => $this->t('Supported formats'),
        );
      }
      if ($permission = $route->getRequirement('_permission')) {
        if (empty($this->permissions)) {
          $this->permissions = $this->permissionHandler->getPermissions();
        }
        if (!empty($this->permissions[$permission])) {
          $return[$route_name]['permisisons'] = array(
            '#theme' => 'item_list',
            '#items' => array($this->permissions[$permission]['title']),
            '#title' => $this->t('Required permissions'),
          );
        }
      }

      if ($parameters = $route->getOption('parameters')) {
        $return[$route_name]['requirements'] = array(
          '#theme' => 'table',
          '#rows' => array(),
          '#caption' => $this->t('Requirements'),
          '#header' => array(
            $this->t('Name'),
            $this->t('Type'),
            $this->t('Required'),
          ),
        );
        foreach ($parameters as $name => $detail) {
          $type = $detail['type'];
          if (strpos($type, 'entity:') === FALSE) {
            // We only handle entity parameters from here onwards.
            continue;
          }
          list(, $entity_type_id) = explode(':', $type);
          $entity_type = $this->entityManager()->getDefinition($entity_type_id);
          $id_field_name = $entity_type->getKey('id');
          $base_fields = $this->entityManager()->getBaseFieldDefinitions($entity_type_id);
          $id_field = $base_fields[$id_field_name];
          $row = array(
            $name,
            $id_field->getType(),
            'TRUE',
          );
          $return[$route_name]['requirements']['#rows'][] = $row;

          if ($route->getMethods() == array('DELETE') || $route->getMethods() == array('GET')) {
            // No body for these two verbs.
            continue;
          }
          $return[$route_name]['detail'] = array(
            '#theme' => 'table',
            '#rows' => array(),
            '#empty' => $this->t('No parameters found'),
            '#caption' => $this->t('Parameters'),
            '#header' => array(
              $this->t('Name'),
              $this->t('Type'),
              $this->t('Required'),
              $this->t('Description'),
            ),
          );
          foreach ($base_fields as $field_name => $field) {
            $row = array(
              $field_name,
              $field->getType(),
              $field->isRequired() ? 'TRUE' : 'FALSE',
              $field->getDescription(),
            );
            $return[$route_name]['detail']['#rows'][] = $row;
          }
          $bundle_info = $this->entityManager()->getBundleInfo($entity_type_id);
          if (count($bundle_info) > 1) {
            // Multiple bundles.
            foreach (array_keys($bundle_info) as $bundle) {
              $label = $bundle_info[$bundle]['label'];
              $row = array(
                'cells' => array(
                  'colspan' => 4,
                  'data' => $this->t('Fields available for !label (!bundle_key = !bundle)', array(
                      '!label' => $label,
                      '!bundle_key' => $entity_type->getKey('bundle'),
                      '!bundle' => $bundle,
                    )
                  ),
                  'header' => TRUE,
                ),
              );
              $return[$route_name]['detail']['#rows'][] = $row;
              $field_definitions = $this->entityManager()->getFieldDefinitions($entity_type_id, $bundle);
              foreach ($field_definitions as $field_name => $field) {
                if (!empty($base_fields[$field_name])) {
                  continue;
                }
                $row = array(
                  $field_name,
                  $field->getType(),
                  $field->isRequired() ? 'TRUE' : 'FALSE',
                  $field->getDescription(),
                );
                $return[$route_name]['detail']['#rows'][] = $row;
              }
            }
          }
          else {
            // Single bundle.
            $bundle_keys = array_keys($bundle_info);
            $bundle = array_shift($bundle_keys);
            $field_definitions = $this->entityManager()->getFieldDefinitions($entity_type_id, $bundle);
            foreach ($field_definitions as $field_name => $field) {
              if (!empty($base_fields[$field_name])) {
                continue;
              }
              $row = array(
                $field_name,
                $field->getType(),
                $field->isRequired() ? 'TRUE' : 'FALSE',
                $field->getDescription(),
              );
              $return[$route_name]['detail']['#rows'][] = $row;
            }
          }
        }
      }
    }
    if ($first) {
      // No matching enabled routes for this path, return not found.
      throw new NotFoundHttpException();
    }
    $return['link'] = array(
      '#markup' => $this->l($this->t('Back to overview'), Url::fromRoute('rest_api_doc.documentation_summary')),
    );

    return $return;
  }

  /**
   * Parses the phpDoc summary line of a controller method.
   *
   * @param \ReflectionMethod $method
   *   The reflected controller method.
   *
   * @return string
   *   The parsed phpDoc summary line.
   */
  protected function parseMethodDocBlock(\ReflectionMethod $method) {
    $php_doc = $method->getDocComment();
    // Normalize line endings.
    $php_doc = preg_replace('/\r\n|\r/', '\n', $php_doc);
    // Strip leading and trailing doc block lines.
    $php_doc = substr($php_doc, 4, -4);

    // Extract actual phpDoc content.
    $php_doc = explode("\n", $php_doc);
    array_walk($php_doc, function (&$value) {
      $value = trim($value, "* /\n");
    });

    // Extract summary; allowed to it wrap and continue on next line.
    list($summary) = explode("\n\n", implode("\n", $php_doc));
    return $summary;
  }

}
