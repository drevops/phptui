<?php

/**
 * @file
 * Option descriptions: a contextual line for the highlighted option.
 *
 * Each option may carry a description, shown as a secondary line beneath the
 * list and updated as the highlight moves. It is presentational only - the
 * field still collects the selected value - and it is available on the select,
 * search, suggest and reorder widgets. Declare it per option with
 * ->option(..., description: ...), or resolve it for the highlighted value with
 * ->describeOptions().
 *
 * Usage:
 *   php playground/02-widgets-select-descriptions.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Option descriptions')
  ->panel('main', 'Select', function (PanelBuilder $p): void {
    // A description travels with each option and shows for the highlighted one.
    $p->select('fruit', 'Fruit')->default('apple')
      ->option('apple', 'Apple', description: 'Crisp and sweet, the everyday choice.')
      ->option('banana', 'Banana', description: 'Rich in potassium; ripens off the tree.')
      ->option('cherry', 'Cherry', description: 'Short season; best eaten fresh.');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
