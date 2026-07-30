<?php

/**
 * @file
 * ASCII glyphs: the textual fallback for non-Unicode terminals.
 *
 * Fields pull their glyphs from the theme as Unicode/ASCII pairs - the
 * radio, checkbox, marker, caret and scroll indicators all degrade to plain
 * characters. Unicode support is auto-detected from the locale (LC_ALL,
 * LC_CTYPE, LANG); ->unicode(FALSE) forces the textual set to see it on any
 * terminal, and ->unicode(TRUE) forces Unicode back on.
 *
 * Usage:
 *   php playground/11-display-modes-ascii.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\EngineException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Display modes demo')
  ->panel('appearance', 'Appearance', function (PanelBuilder $p): void {
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'cherry' => 'Cherry',
      'grape' => 'Grape',
    ]);
    $p->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // Force ASCII glyphs; NULL would return to locale auto-detection.
  $answers = (new Tui($form))->unicode(FALSE)->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (EngineException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
