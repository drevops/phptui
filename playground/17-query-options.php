<?php

/**
 * @file
 * Options resolved from the live query, as the user types.
 *
 * Where ->options() resolves one fixed list, ->optionsFrom() is called again
 * every time the query changes, so the candidates can come from a backend that
 * does the filtering itself. The field shows a themed "Loading…" while a call
 * is in flight, a burst of typing costs one call rather than one per character,
 * and a query already answered is served from a cache. ->minQuery() keeps the
 * field quiet until the query is worth sending.
 *
 * Usage:
 *   php playground/17-query-options.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// Stands in for a remote backend: it matches for itself and takes its time.
$pantry = static function (string $query): array {
  usleep(300000);

  $produce = [
    'carrot' => 'Carrot',
    'celery' => 'Celery',
    'onion' => 'Onion',
    'pepper' => 'Pepper',
    'potato' => 'Potato',
    'spinach' => 'Spinach',
    'tomato' => 'Tomato',
  ];

  return array_filter($produce, static fn(string $label): bool => stripos($label, $query) !== FALSE);
};

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p) use ($pantry): void {
    $p->text('name', 'Order name')->required();

    // Called on every query change, not once: the list follows what is typed.
    $p->search('veg', 'Vegetable')->optionsFrom($pantry);

    // The same, but silent until two characters are typed - so the backend is
    // never asked to list everything.
    $p->suggest('extra', 'Add another')->optionsFrom($pantry)->minQuery(2);
  });

try {
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
