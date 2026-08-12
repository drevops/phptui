<?php

/**
 * @file
 * Malformed plural fixture: every invalid shape is ignored, strings survive.
 */

declare(strict_types=1);

use DrevOps\PhpTui\Translation\Translator;

return [
  // A non-closure under the rule key is ignored (the default rule applies).
  Translator::PLURAL_RULE => 'not a closure',
  // A list with a non-string element is not a valid form set.
  '@count items selected' => ['@count Dinge', 123],
  // An associative array is not a list of forms.
  'assoc' => ['a' => 'x'],
  // An empty array carries no forms.
  'empty' => [],
  // A plain string entry is still kept.
  'Submit' => 'Senden',
];
