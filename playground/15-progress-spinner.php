<?php

/**
 * @file
 * Progress as a spinner: feedback for slow work of unknown length.
 *
 * With a NULL total, progress() renders an indeterminate spinner - an animated
 * glyph beside a caption while the callback runs - and returns the callback's
 * result. The callback drives it with advance(), which ticks the animation one
 * frame. The active theme draws the glyph in its own accent and Unicode/ASCII
 * mode (set one with ->theme(...)); off a TTY, piped or redirected, it degrades
 * to a single plain caption line with no control sequences.
 *
 * Usage:
 *   php playground/15-progress-spinner.php
 *
 *   # Off a TTY it degrades to one plain line:
 *   php playground/15-progress-spinner.php 2>&1 | cat
 *
 *   # ASCII frames when the locale is not UTF-8:
 *   LC_ALL=C php playground/15-progress-spinner.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Primitive\Progress;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Quick start')
  ->panel('New order', function (PanelBuilder $p): void {
    $p->text('Order name')->required();
  });

$tui = new Tui($form);

// A NULL total is an indeterminate spinner; advance() ticks the animation.
$baskets = $tui->progress(NULL, 'Counting the baskets', function (Progress $progress): array {
  $counted = [];

  foreach (['Apple', 'Pear', 'Plum', 'Cherry', 'Apricot', 'Peach'] as $fruit) {
    usleep(180000);
    $counted[] = $fruit;
    $progress->advance();
  }

  return $counted;
});

echo 'Counted ' . count($baskets) . ' baskets: ' . implode(', ', $baskets) . '.' . PHP_EOL;
