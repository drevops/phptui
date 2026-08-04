<?php

/**
 * @file
 * Progress field: a panel row that runs work when activated, drawn in place.
 *
 * Selecting the row and pressing Enter runs its work; the bar fills in the row
 * itself as each advance() lands (drop ->steps() for an indeterminate spinner).
 * The row collects no value - it is a place to do work inside the form, beside
 * the fields it depends on. Unattended runs skip it.
 *
 * Usage:
 *   php playground/02-fields-progress.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$items = ['Apple', 'Carrot', 'Tomato', 'Spinach', 'Pear', 'Beet'];

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Progress field')
  ->panel('main', 'Progress', function (PanelBuilder $p) use ($items): void {
    $p->progress('pack', 'Packing the box')->steps(count($items))->run(function (ProgressReporter $reporter) use ($items): void {
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
