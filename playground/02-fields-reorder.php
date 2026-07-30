<?php

/**
 * @file
 * Reorder field: rank a fixed list by moving items.
 *
 * Space picks the highlighted item up, arrows carry it through the list,
 * Space drops it, Enter accepts. The field collects the option values in
 * their final order - every option, always, just rearranged.
 *
 * Usage:
 *   php playground/02-fields-reorder.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Reorder field')
  ->panel('main', 'Reorder', function (PanelBuilder $p): void {
    // The declared order is the starting order.
    $p->reorder('basket', 'Basket')->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
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
