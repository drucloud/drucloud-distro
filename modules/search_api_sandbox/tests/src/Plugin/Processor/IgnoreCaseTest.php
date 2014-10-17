<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api\Plugin\Processor\IgnoreCaseTest.
 */

namespace Drupal\Tests\search_api\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Ignore case" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\IgnoreCase
 */
class IgnoreCaseTest extends UnitTestCase {

  use ProcessorTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->processor = new IgnoreCase(array(), 'string', array());
  }

  /**
   * Tests the process() method.
   *
   * @dataProvider processDataProvider
   */
  public function testProcess($passedString, $expectedValue) {
    $this->invokeMethod('process', array(&$passedString));
    $this->assertEquals($passedString, $expectedValue);
  }

  /**
   * Provides sets of arguments for testProcess().
   *
   * @return array[]
   *   Arrays of arguments for testProcess().
   */
  public function processDataProvider() {
    return array(
      array('Foo bar', 'foo bar'),
      array('foo Bar', 'foo bar'),
      array('Foo Bar', 'foo bar'),
      array('Foo bar BaZ, ÄÖÜÀÁ<>»«.', 'foo bar baz, äöüàá<>»«.')
    );
  }

}
