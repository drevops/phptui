<?php

/**
 * @file
 * Confirm field: a Yes/No toggle collecting a bool.
 *
 * Arrows or Space switch the highlighted answer, y/n set it directly, Enter
 * accepts.
 *
 * Usage:
 *   php playground/02-fields-confirm.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Confirm field')
  ->panel('Confirm', function (PanelBuilder $p): void {
    $p->confirm('Organic only?')->default(TRUE);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
