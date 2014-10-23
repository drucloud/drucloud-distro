<?php

/**
 * @file
 * Contains \Drupal\rest_api_doc\Form\RestApiDocSettingsForm.
 */

namespace Drupal\rest_api_doc\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains a settings form for configuring the rest_api_doc module.
 */
class RestApiDocSettingsForm extends ConfigFormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('router.route_provider')
    );
  }

  /**
   * Creates a new RestApiDocSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, RouteProviderInterface $route_provider) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->routeProvider = $route_provider;
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rest_api_doc_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('rest_api_doc.settings');
    $enabled_route_names = $settings->get('routes');
    $available_route_names = $this->state->get('rest_api_doc.rest_route_names');
    if (empty($available_route_names)) {
      return array(
        'no_routes' => array(
          '#markup' => $this->t('No REST enabled routes exist, please configure your REST end-points'),
        ),
      );
    }
    else {
      $routes = $this->routeProvider->getRoutesByNames($available_route_names);
      $descriptions = array();
      foreach ($routes as $route_name => $route) {
        $descriptions[$route_name] = $route_name . ' (' . $route->getPath() . ')';
      }
      $form['routes'] = array(
        '#type' => 'checkboxes',
        '#title' => $this->t('Enabled routes'),
        '#description' => $this->t('Provide documentation for the following route names'),
        '#options' => array_combine($available_route_names, $descriptions),
        '#default_value' => $enabled_route_names,
      );
      $form['overview'] = array(
        '#type' => 'textarea',
        '#default_value' => $settings->get('overview'),
        '#title' => $this->t('REST API overview'),
        '#description' => $this->t('Description to show on summary page. You may use site-wide tokens and some markup.'),
      );
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('rest_api_doc.settings')
      ->set('overview', $form_state->getValue('overview'))
      ->set('routes', array_keys(array_filter($form_state->getValue('routes'))))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
