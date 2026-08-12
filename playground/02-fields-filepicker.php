<?php

/**
 * @file
 * File picker field: browse the filesystem for one path.
 *
 * Arrows move, Right enters a directory, Left returns to its parent, Enter
 * selects. The form points the picker at a small fixture tree with
 * ->startIn(), limits it to files with ->filesOnly(), to CSV with
 * ->extensions() and to a size with ->maxSize() - a pick that breaks a limit
 * is rejected inline; ->directoriesOnly() and ->showHidden() are the other
 * filters. The field collects the selected path as a string.
 *
 * Usage:
 *   php playground/02-fields-filepicker.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('File picker field')
  ->panel('main', 'File picker', function (PanelBuilder $p): void {
    $p->filePicker('price_list', 'Price list')->startIn(__DIR__ . '/sample-project')->filesOnly()->extensions(['csv'])->maxSize(50);
  });

try {
  // Interactive on a terminal; resolved (empty) when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
