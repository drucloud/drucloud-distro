<?php
 
/**
 * @file
 * Contains Drupal\keithyau\keithyauService.
 */
 
namespace Drupal\keithyau;
 
class keithyauService {
   
  protected $demo_value;
   
  public function __construct() {
    $this->demo_value = 'Upchuk';
  }
   
  public function getDemoValue() {
    return $this->demo_value;
  }
   
}
