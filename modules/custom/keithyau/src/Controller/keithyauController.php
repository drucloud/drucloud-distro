<?php
 
/**
 * @file
 * Contains \Drupal\keithyau\Controller\keithyauController.
 */
 
namespace Drupal\keithyau\Controller;
 
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
 
/**
 * keithyauController.
 */
class keithyauController extends ControllerBase {
   
  protected $keithyauService;
   
  /**
   * Class constructor.
   */
  public function __construct($keithyauService) {
    $this->keithyauService = $keithyauService;
  }
   
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('keithyau.keithyau_service')
    );
  }
   
  /**
   * Generates an example page.
   */
  public function keithyau() {
    return array(
      //'#markup' => t('Hello @value!', array('@value' => $this->keithyauService->getkeithyauValue())),
      '#markup' => t('Hello keithyau!'),
    );
  }
}
