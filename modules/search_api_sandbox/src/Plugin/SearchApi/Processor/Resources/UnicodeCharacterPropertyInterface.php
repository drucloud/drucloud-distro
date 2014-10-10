<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\UnicodeCharacterPropertyInterface.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

/**
 * Defines an interface for classes representing a Unicode character property.
 */
interface UnicodeCharacterPropertyInterface {

  /**
   * Returns a regular expression matching this character class.
   *
   * @return string
   *   A PCRE regular expression.
   */
  public static function getRegularExpression();

}
