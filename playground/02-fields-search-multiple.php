<?php

/**
 * @file
 * Search field with ->multiple(): fuzzy filter plus checkboxes.
 *
 * Typing narrows the ranked list, Space toggles the highlighted option and
 * the filter stays put, so several picks chain naturally: type, Space, type,
 * Space, Enter. The field collects the checked values.
 *
 * Usage:
 *   php playground/02-fields-search-multiple.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('MultiSearch field')
  ->panel('MultiSearch', function (PanelBuilder $p): void {
    $p->search('Basket')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
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
