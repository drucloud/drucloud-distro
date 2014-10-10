<?php

/**
 * @file
 * Contains Drupal\Tests\search_api\Plugin\Processor\TestFieldsProcessorPlugin.
 */

namespace Drupal\Tests\search_api\Plugin\Processor;

use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Test class for processors that work on individual fields.
 */
class TestFieldsProcessorPlugin extends FieldsProcessorPluginBase {

  /**
   * Array of method overrides.
   *
   * @var callable[]
   */
  protected $methodOverrides = array();

  /**
   * Creates a valid tokenized_text field value for testing purposes.
   *
   * @param string $value
   *   The value to be tokenized.
   * @param int|null $score
   *   The score to set, or NULL to omit setting a score.
   *
   * @return array[]
   *   A valid tokenized_text field value.
   */
  public static function createTokenizedText($value, $score = 1) {
    $return = array();
    if (isset($score)) {
      $token['score'] = $score;
    }
    foreach (explode(' ', $value) as $word) {
      $token['value'] = $word;
      $return[] = $token;
    }
    return $return;
  }

  /**
   * Overrides a method in this processor.
   *
   * @param string $method
   *   The name of the method to override.
   * @param callable|null $override
   *   The new code of the method, or NULL to use the default.
   */
  public function setMethodOverride($method, $override = NULL) {
    $this->methodOverrides[$method] = $override;
  }

  /**
   * {@inheritdoc}
   */
  protected function testField($name, FieldInterface $field) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($name, $field);
    }
    return parent::testField($name, $field);
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      return $this->methodOverrides[__FUNCTION__]($type);
    }
    return parent::testType($type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, &$type) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value, $type);
      return;
    }
    parent::processFieldValue($value, $type);
  }

  /**
   * {@inheritdoc}
   */
  protected function processKey(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processKey($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFilterValue(&$value) {
    if (isset($this->methodOverrides[__FUNCTION__])) {
      $this->methodOverrides[__FUNCTION__]($value);
      return;
    }
    parent::processFilterValue($value);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    if (isset($value)) {
      $value = "*$value";
    }
    else {
      $value = 'undefined';
    }
  }

}
