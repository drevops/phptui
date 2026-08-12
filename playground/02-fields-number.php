<?php

/**
 * @file
 * Number field: integer entry accepted as an int.
 *
 * Digits (with an optional leading minus) are the only accepted input; Up and
 * Down nudge the value by the step. The min/max bounds are validated on
 * accept, so an out-of-range value cannot be submitted.
 *
 * Usage:
 *   php playground/02-fields-number.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Number field')
  ->panel('main', 'Number', function (PanelBuilder $p): void {
    $p->number('weight', 'Basket weight (g)')->default(1200)->min(200)->max(9000)->step(100);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
