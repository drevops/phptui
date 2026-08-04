<?php

/**
 * @file
 * File picker ->multiple() with selection limits: a minimum and maximum count.
 *
 * ->minSelections(2)->maxSelections(3) bounds how many paths may be checked.
 * The active limit shows as a hint above the browser, and accepting a count
 * outside the range is rejected inline until it is satisfied. The field still
 * collects the list of chosen paths.
 *
 * Usage:
 *   php playground/02-fields-filepicker-multiple-limited.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('File picker field')
  ->panel('main', 'File picker', function (PanelBuilder $p): void {
    // Pick at least two and at most three paths.
    $p->filePicker('price_lists', 'Price lists')->multiple()->minSelections(2)->maxSelections(3)->startIn(__DIR__ . '/sample-project');
  });

try {
  // Interactive on a terminal; resolved (empty) when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
