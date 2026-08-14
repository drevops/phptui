<?php

/**
 * @file
 * Progress row: a block that runs work when activated, drawn in place.
 *
 * Selecting the row and pressing Enter runs its work; the bar fills in the row
 * itself as each advance() lands (drop ->steps() for an indeterminate spinner).
 * The row collects no value - it is a place to do work inside the form, beside
 * the fields it depends on, which is what makes it a block of its own rather
 * than a kind of field. Unattended runs skip it. The progress primitive
 * (playground/15-progress-*) is the same indicator for slow work that happens
 * around a form rather than in one.
 *
 * Usage:
 *   php playground/02-progress-row.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Primitive\ProgressReporter;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$items = ['Apple', 'Carrot', 'Tomato', 'Spinach', 'Pear', 'Beet'];

// One block on one panel: the smallest form that exercises the row.
$form = Form::create('Progress row')
  ->panel('Progress', function (PanelBuilder $p) use ($items): void {
    $p->progress('Packing the box')->steps(count($items))->work(function (ProgressReporter $reporter) use ($items): void {
      foreach ($items as $item) {
        usleep(220000);
        $reporter->advance('packed ' . $item);
      }
    });
  });

try {
  // Interactive on a terminal; the row is skipped when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
