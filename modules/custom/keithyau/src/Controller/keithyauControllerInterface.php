<?php

namespace Drupal\keithyau\Controller;


use Drupal\Core\Controller\ControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class keithyauController implements ControllerInterface {

  public static function create(ContainerInterface $container) {
     return static($container->get('module_handler'));
  }

  public function keithyauPage() {
    $build = array(
      '#type' => 'markup',
      '#markup' => t('hellp world'),
    );
    return $build;
  }

  $service = \Drupal::service('keithyau.keithyau_service');

}
