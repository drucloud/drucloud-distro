<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zp.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

/**
 * Represents characters of the Unicode category "Zp" ("Separator, Paragraph").
 */
class Zp implements UnicodeCharacterPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{2029}';
  }

}
