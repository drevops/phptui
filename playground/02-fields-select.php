<?php

/**
 * @file
 * Select field: single choice from a list of options.
 *
 * Arrows move the highlight, Enter accepts it; a list longer than
 * ->pageSize() pages around the cursor. The field collects the selected
 * option value (a string), never the label.
 *
 * Usage:
 *   php playground/02-fields-select.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Select field')
  ->panel('Select', function (PanelBuilder $p): void {
    // Options are a value => label map; the default names a value.
    $p->select('Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
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
