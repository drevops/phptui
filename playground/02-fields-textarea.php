<?php

/**
 * @file
 * Textarea field: multi-line input.
 *
 * Enter inserts a newline and arrows move between lines, so Tab accepts
 * instead. With ->externalEditor(), Ctrl-E hands the draft to $VISUAL/$EDITOR
 * and reads the saved file back into the field.
 *
 * Usage:
 *   php playground/02-fields-textarea.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Textarea field')
  ->panel('Textarea', function (PanelBuilder $p): void {
    $p->textarea('Tasting notes')->default('Crisp and sweet' . chr(10) . 'Hint of citrus')->externalEditor();
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
