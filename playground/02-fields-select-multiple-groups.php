<?php

/**
 * @file
 * Multi-select field with option groups spanning two categories.
 *
 * The same ->heading(), ->separator() and disabled-option rows as the single
 * select, under ->multiple(): Space toggles, the cursor skips the
 * non-selectable rows, and the field collects the checked values.
 *
 * Usage:
 *   php playground/02-fields-select-multiple-groups.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('MultiSelect with groups')
  ->panel('main', 'MultiSelect', function (PanelBuilder $p): void {
    $p->select('basket', 'Basket')->multiple()->default(['apple'])
      ->heading('Fruit')
      ->option('apple', 'Apple')
      ->option('banana', 'Banana')
      ->separator()
      ->heading('Vegetables')
      ->option('carrot', 'Carrot')
      ->option('tomato', 'Tomato')
      ->option('leek', 'Leek', disabled: TRUE, disabled_reason: 'out of season');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
