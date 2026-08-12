<?php

/**
 * @file
 * Mode auto-detection: the palette follows the terminal background.
 *
 * With no 'mode' option the interactive TUI reads the terminal background -
 * an OSC 11 query first, the COLORFGBG environment variable as the fallback,
 * dark as the last resort - and picks the dark or light palette to match.
 * Switch your terminal between a light and a dark colour scheme and re-run
 * this script to watch the palette follow. mode-forced.php pins it instead.
 *
 * Usage:
 *   php playground/11-display-modes-mode-auto.php
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
    $p->text('name', 'Order name')->default('Weekly');
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'cherry' => 'Cherry',
      'grape' => 'Grape',
    ]);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // No theme, no options: the default theme with every display option
  // auto-detected - colour support, Unicode glyphs and the dark/light mode.
  $answers = (new Tui($form))->run();
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
