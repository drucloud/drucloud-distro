<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api\Plugin\Processor\StopwordsTest.
 */

namespace Drupal\Tests\search_api\Plugin\Processor;

use Drupal\search_api\Plugin\SearchApi\Processor\Stopwords;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Stopwords" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\SearchApi\Processor\Stopwords
 */
class StopwordsTest extends UnitTestCase {

  use ProcessorTestTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new Stopwords(array(), 'stopwords', array());;
  }

  /**
   * Tests the process() method of the Stopwords processor.
   *
   * @dataProvider processDataProvider
   */
  public function testProcess($passedString, $expectedString, $stopwordsConfig) {
    $this->processor->setConfiguration(array('stopwords' => $stopwordsConfig));
    $this->invokeMethod('process', array(&$passedString));
    $this->assertEquals($passedString, $expectedString);
  }

  /**
   * Data provider for testStopwords().
   *
   * Processor checks for exact case, and tokenized content.
   */
  public function processDataProvider() {
    return array(
      array(
        'or',
        '',
        array('or'),
      ),
       array(
        'orb',
        'orb',
        array('or'),
      ),
      array(
        'for',
        'for',
        array('or'),
      ),
      array(
        'ordor',
        'ordor',
        array('or'),
      ),
      array(
        'ÄÖÜÀÁ<>»«û',
        'ÄÖÜÀÁ<>»«û',
        array('stopword1', 'ÄÖÜÀÁ<>»«', 'stopword3'),
      ),
      array(
        'ÄÖÜÀÁ',
        '',
        array('stopword1', 'ÄÖÜÀÁ', 'stopword3'),
      ),
      array(
        'ÄÖÜÀÁ stopword1',
        'ÄÖÜÀÁ stopword1',
        array('stopword1', 'ÄÖÜÀÁ', 'stopword3'),
      ),
    );
  }

  /**
   * Tests the processor's preprocessSearchQuery() method.
   */
  public function testPreprocessSearchQuery() {
    $index = $this->getMock('Drupal\search_api\Index\IndexInterface');
    $index->expects($this->any())
      ->method('status')
      ->will($this->returnValue(TRUE));
    $index->expects($this->any())
      ->method('getFields')
      ->will($this->returnValue(array()));
    /** @var \Drupal\search_api\Index\IndexInterface $index */

    $this->processor->setIndex($index);
    $query = Utility::createQuery($index);
    $keys = array('#conjunction' => 'AND', 'foo', 'bar', 'bar foo');
    $query->keys($keys);

    $this->processor->setConfiguration(array('stopwords' => array('foobar', 'bar', 'barfoo')));
    $this->processor->preprocessSearchQuery($query);
    unset($keys[1]);
    $this->assertEquals($keys, $query->getKeys());

    $results = Utility::createSearchResultSet($query);
    $this->processor->postprocessSearchResults($results);
    $this->assertEquals(array('bar'), $results->getIgnoredSearchKeys());
  }

}
