<?php

/**
 * @file
 * Forcing the mode: pin the light or dark palette, detection off.
 *
 * An explicit 'mode' option overrides background detection - here the light
 * palette even on a dark terminal; Mode::Dark pins it the other way. The
 * closed set of modes is the Mode enum, so an invalid value cannot compile.
 *
 * Usage:
 *   php playground/11-display-modes-mode-forced.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Display modes demo')
  ->panel('appearance', 'Appearance', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly');
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'cherry' => 'Cherry',
      'grape' => 'Grape',
    ]);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // Force the light palette on the default theme; swap in Mode::Dark to pin
  // the dark one.
  $answers = (new Tui($form))->theme('default', ['mode' => Mode::Light])->run();
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
