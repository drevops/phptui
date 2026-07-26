<?php

/**
 * @file
 * Progress as a determinate bar: step-by-step feedback of known length.
 *
 * With a known total, progress() renders a filling bar with a step count and a
 * label, and returns the callback's result. The callback drives it with
 * advance(), which fills one step and replaces the trailing label. The active
 * theme draws the bar in its own accent and Unicode/ASCII mode (set one with
 * ->theme(...)); off a TTY, piped or redirected, it degrades to a single plain
 * caption line with no control sequences.
 *
 * Usage:
 *   php playground/15-progress-bar.php
 *
 *   # Off a TTY it degrades to one plain line:
 *   php playground/15-progress-bar.php 2>&1 | cat
 *
 *   # ASCII cells when the locale is not UTF-8:
 *   LC_ALL=C php playground/15-progress-bar.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
  });

$tui = new Tui($form);

$items = ['Apple', 'Carrot', 'Tomato', 'Spinach', 'Pear', 'Beet'];

// A known total is a determinate bar; advance() fills a step, sets the label.
$packed = $tui->progress(count($items), 'Packing the order', function (Progress $progress) use ($items): array {
  $done = [];

  foreach ($items as $item) {
    usleep(220000);
    $done[] = $item;
    $progress->advance('packed ' . $item);
  }

  return $done;
});

echo 'Packed ' . count($packed) . ' items into the box.' . PHP_EOL;
