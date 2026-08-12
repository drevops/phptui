<?php

/**
 * @file
 * Ukrainian plural fixture: a catalog-supplied rule and its three forms.
 */

declare(strict_types=1);

use DrevOps\PhpTui\Translation\Translator;

return [
  Translator::PLURAL_RULE => static function (int $count): int {
    $mod10 = $count % 10;
    $mod100 = $count % 100;

    if ($mod10 === 1 && $mod100 !== 11) {
      return 0;
    }

    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
      return 1;
    }

    return 2;
  },
  '@count items selected' => [
    '@count елемент вибрано',
    '@count елементи вибрано',
    '@count елементів вибрано',
  ],
  // Deliberately two forms though the rule can return 'many' (index 2), to
  // exercise the fallback when a translation supplies fewer forms than needed.
  '@count boxes' => [
    '@count коробка',
    '@count коробки',
  ],
  'Submit' => 'Надіслати',
];
