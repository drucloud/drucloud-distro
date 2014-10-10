<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\SearchApi\Processor\Resources\Zl.
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;

/**
 * Represents characters of the Unicode category "Zl" ("Separator, Line").
 */
class Zl implements UnicodeCharacterPropertyInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRegularExpression() {
    return
      '\x{2028}';
  }

}
