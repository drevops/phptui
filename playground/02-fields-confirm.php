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

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Confirm field')
  ->panel('main', 'Confirm', function (PanelBuilder $p): void {
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
