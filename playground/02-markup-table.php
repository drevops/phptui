<?php

/**
 * @file
 * Markup laid out as a table: an aligned, bordered grid of tabular context.
 *
 * A table is not a block of its own - it is markup drawn as a grid, declared
 * with ->table(headers, rows) beneath the title and body. The grid honours the
 * active theme - its border style, colour and Unicode switches - and its cells
 * take the same `{{field}}` templating the title and body do. Like all markup
 * it is presentational: the cursor skips it, it collects nothing, and headless
 * runs omit it, so the JSON here is empty.
 *
 * Usage:
 *   php playground/02-markup-table.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$headers = ['Fruit', 'Color', 'In stock'];
$rows = [
  ['Apple', 'Red', '12'],
  ['Pear', 'Green', '5'],
  ['Plum', 'Purple', '120'],
];

$form = Form::create('Markup table')
  ->panel('Stock', function (PanelBuilder $p) use ($headers, $rows): void {
    $p->note('Basket contents')->body('Everything picked so far:')->table($headers, $rows);
  });

try {
  // Interactive on a terminal; headless otherwise - either way the markup (and
  // its grid) is absent from the JSON, which carries no collected value.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
