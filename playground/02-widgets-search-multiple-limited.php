<?php

/**
 * @file
 * Search ->multiple() with selection limits: a minimum and maximum count.
 *
 * ->minSelections(2)->maxSelections(3) bounds how many options may be checked.
 * The active limit shows as a hint above the list, and accepting a count
 * outside the range is rejected inline until it is satisfied. The field still
 * collects the list of checked values.
 *
 * Usage:
 *   php playground/02-widgets-search-multiple-limited.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the widget.
$form = Form::create('Bounded MultiSearch')
  ->panel('main', 'MultiSearch', function (PanelBuilder $p): void {
    // Pick at least two and at most three of the options.
    $p->search('basket', 'Basket')->multiple()->minSelections(2)->maxSelections(3)->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
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
