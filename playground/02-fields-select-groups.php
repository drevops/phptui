<?php

/**
 * @file
 * Select field with option groups: headings, separators, disabled options.
 *
 * Options declared one by one with ->option() can be interleaved with
 * ->heading() and ->separator() rows; a disabled option shows its reason
 * beside the label. The non-selectable rows are visual only - the cursor
 * skips them.
 *
 * Usage:
 *   php playground/02-fields-select-groups.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Select with groups')
  ->panel('Select', function (PanelBuilder $p): void {
    $p->select('Fruit')->default('apple')
      ->heading('Fruit')
      ->option('apple', 'Apple')
      ->option('banana', 'Banana')
      ->separator()
      ->option('cherry', 'Cherry', disabled: TRUE, disabled_reason: 'out of season');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
