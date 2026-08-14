<?php

/**
 * @file
 * Search field: single choice behind a visible filter line.
 *
 * Typing fuzzy-matches and ranks the option labels - exact and prefix matches
 * lead - and Enter accepts the highlighted option. Where select scrolls a
 * list, search narrows it; prefer it once a list is long enough that typing
 * beats arrowing.
 *
 * Usage:
 *   php playground/02-fields-search.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Search field')
  ->panel('Search', function (PanelBuilder $p): void {
    $p->search('Vegetable')->default('carrot')->options([
      'carrot' => 'Carrot',
      'potato' => 'Potato',
      'onion' => 'Onion',
      'pepper' => 'Pepper',
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
