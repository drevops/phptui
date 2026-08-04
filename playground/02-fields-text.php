<?php

/**
 * @file
 * Text field: single-line input with a movable caret.
 *
 * Type to insert at the caret, arrows move it, Backspace deletes, Enter
 * accepts. The field collects a string.
 *
 * Usage:
 *   php playground/02-fields-text.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Text field')
  ->panel('main', 'Text', function (PanelBuilder $p): void {
    // ->complete() adds Tab-completion over a fixed word list; typing stays
    // free-form, the list only helps.
    $p->text('item', 'Item')->default('Pear')->complete(['Pear', 'Peach', 'Plum']);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
