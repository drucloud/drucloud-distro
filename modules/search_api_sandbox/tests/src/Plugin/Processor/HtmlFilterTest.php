<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api\Plugin\Processor\HtmlFilterTest.
 */

namespace Drupal\Tests\search_api\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\HtmlFilter;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "HTML filter" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\HtmlFilter
 */
class HtmlFilterTest extends UnitTestCase {

  use ProcessorTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->processor = new HtmlFilter(array(), 'html_filter', array());
  }

  /**
   * Tests processFieldValue method with title fetching enabled.
   *
   * @dataProvider titleConfigurationDataProvider
   */
  public function testTitleConfiguration($passedString, $expectedValue, $titleConfig) {
    $this->processor->setConfiguration(array('tags' => array(), 'title' => $titleConfig, 'alt' => FALSE));
    $this->invokeMethod('processFieldValue', array(&$passedString, 'text'));
    $this->assertEquals($expectedValue, $passedString);

  }

  /**
   * Data provider for testTitleConfiguration().
   */
  public function titleConfigurationDataProvider() {
    return array(
      array('word', 'word', FALSE),
      array('word', 'word', TRUE),
      array('<div>word</div>', 'word', TRUE),
      array('<div title="TITLE">word</div>', 'TITLE word', TRUE),
      array('<div title="TITLE">word</div>', 'word', FALSE),
      array('<div data-title="TITLE">word</div>', 'word', TRUE),
      array('<div title="TITLE">word</a>', 'TITLE word', TRUE),
    );
  }

  /**
   * Tests processFieldValue method with alt fetching enabled.
   *
   * @dataProvider altConfigurationDataProvider
   */
  public function testAltConfiguration($passedString, $expectedValue, $altBoost) {
    $this->processor->setConfiguration(array('tags' => array('img' => '2'), 'title' => FALSE, 'alt' => $altBoost));
    $this->invokeMethod('processFieldValue', array(&$passedString, 'text'));
    $this->assertEquals($expectedValue, $passedString);
  }

  /**
   * Data provider method for testAltConfiguration()
   */
  public function altConfigurationDataProvider() {
    return array(
      array('word', array(Utility::createTextToken('word')), FALSE),
      array('word', array(Utility::createTextToken('word')), TRUE),
      array('<img src="href" />word', array(Utility::createTextToken('word')), TRUE),
      array('<img alt="ALT"/> word', array(Utility::createTextToken('ALT', 2), Utility::createTextToken('word')), TRUE),
      array('<img alt="ALT" /> word', array(Utility::createTextToken('word')), FALSE),
      array('<img data-alt="ALT"/> word', array(Utility::createTextToken('word')), TRUE),
      array('<img src="href" alt="ALT" title="Bar" /> word </a>', array(Utility::createTextToken('ALT', 2), Utility::createTextToken('word')), TRUE),
    );
  }

  /**
   * Tests processFieldValue method with tag provided fetching enabled.
   *
   * @dataProvider tagConfigurationDataProvider
   */
  public function testTagConfiguration($passedString, $expectedValue, array $tagsConfig) {
    $this->processor->setConfiguration(array('tags' => $tagsConfig, 'title' => TRUE, 'alt' => TRUE));
    $this->invokeMethod('processFieldValue', array(&$passedString, 'text'));
    $this->assertEquals($expectedValue, $passedString);
  }

  /**
   * Data provider method for testTagConfiguration()
   */
  public function tagConfigurationDataProvider() {
    $complex_test = array(
      '<h2>Foo Bar <em>Baz</em></h2>

<p>Bla Bla Bla. <strong title="Foobar">Important:</strong> Bla.</p>
<img src="/foo.png" alt="Some picture" />
<span>This is hidden</span>',
      array(
        Utility::createTextToken('Foo Bar', 3.0),
        Utility::createTextToken('Baz', 4.5),
        Utility::createTextToken('Bla Bla Bla.', 1.0),
        Utility::createTextToken('Foobar Important:', 2.0),
        Utility::createTextToken('Bla.', 1.0),
        Utility::createTextToken('Some picture', 0.5),
      ),
      array(
        'em' => 1.5,
        'strong' => 2.0,
        'h2' => 3.0,
        'img' => 0.5,
        'span' => 0,
      ),
    );
    $tags_config = array('h2' => '2');
    return array(
      array('h2word', 'h2word', array()),
      array('h2word', array(Utility::createTextToken('h2word')), $tags_config),
      array('foo bar <h2> h2word </h2>', array(Utility::createTextToken('foo bar'), Utility::createTextToken('h2word', 2.0)), $tags_config),
      array('foo bar <h2>h2word</h2>', array(Utility::createTextToken('foo bar'), Utility::createTextToken('h2word', 2.0)), $tags_config),
      array('<div>word</div>', array(Utility::createTextToken('word', 2)), array('div' => 2)),
      $complex_test,
    );
  }

  /**
   * Tests whether strings are correctly handled.
   *
   * String field handling should be completely independent of configuration.
   *
   * @param array $config
   *   The configuration to set on the processor.
   *
   * @dataProvider stringProcessingDataProvider
   */
  public function testStringProcessing(array $config) {
    $this->processor->setConfiguration($config);

    $passedString = '<h2>Foo Bar <em>Baz</em></h2>

<p>Bla Bla Bla. <strong title="Foobar">Important:</strong> Bla.</p>
<img src="/foo.png" alt="Some picture" />
<span>This is hidden</span>';
    $expectedValue = preg_replace('/\s+/', ' ', strip_tags($passedString));

    $this->invokeMethod('processFieldValue', array(&$passedString, 'string'));
    $this->assertEquals($expectedValue, $passedString);
  }

  /**
   * Provides a few sets of HTML filter configuration.
   *
   * @return array
   *   An array of argument arrays for testStringProcessing(), where each array
   *   contains a HTML filter configuration as the only value.
   */
  public function stringProcessingDataProvider() {
    $configs = array();
    $configs[] = array(array());
    $config['tags'] = array(
      'h2' => 2.0,
      'span' => 4.0,
      'strong' => 1.5,
      'p' => 0,
    );
    $configs[] = array($config);
    $config['title'] = TRUE;
    $configs[] = array($config);
    $config['alt'] = TRUE;
    $configs[] = array($config);
    unset($config['tags']);
    $configs[] = array($config);
    return $configs;
  }

}
