<?php

/**
 * @file
 * Toggle field: an inline switch between two labelled values.
 *
 * Arrows or Space flip the switch, the first letter of either label sets it
 * directly, Enter accepts. Where confirm collects a bool, toggle collects one
 * of two declared option values - a two-state enum, not a yes/no.
 *
 * Usage:
 *   php playground/02-fields-toggle.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Toggle field')
  ->panel('main', 'Toggle', function (PanelBuilder $p): void {
    // Exactly two options; the collected value is one of the keys.
    $p->toggle('ripeness', 'Ripeness')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
