<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Pf.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

/**
 * Represents characters of the category "Pf" ("Punctuation, Final quote").
 */
class Pf implements UnicodeCharacterPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{00BB}\x{2019}\x{201D}\x{203A}\x{2E03}\x{2E05}\x{2E0A}' .
      '\x{2E0D}\x{2E1D}\x{2E21}';
  }

}
