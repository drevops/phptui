<?php

/**
 * @file
 * Select field with ->multiple(): any number of checked options.
 *
 * Space toggles the highlighted option, typing narrows the list by substring,
 * Right/Left check or clear everything visible, Enter accepts. The field
 * collects a list of the checked option values.
 *
 * Usage:
 *   php playground/02-fields-select-multiple.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('MultiSelect field')
  ->panel('main', 'MultiSelect', function (PanelBuilder $p): void {
    // The default pre-checks values, so it is a list here.
    $p->select('basket', 'Basket')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
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
