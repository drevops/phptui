<?php

/**
 * @file
 * Colour off: the TUI degrades to plain text.
 *
 * Colour support is auto-detected - the NO_COLOR convention and TERM=dumb
 * switch it off - and ->color(FALSE) forces it off to see the plain
 * rendering on any terminal. Selection falls back to text markers, so the
 * form stays fully usable without a single ANSI style.
 *
 * Usage:
 *   php playground/11-display-modes-no-color.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

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
  // Force colour off; NULL would return to auto-detection.
  $answers = (new Tui($form))->color(FALSE)->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
