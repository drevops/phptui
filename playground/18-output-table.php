<?php

/**
 * @file
 * Output as a table: an aligned grid drawn outside any form.
 *
 * The table() call lays headers and rows into a bordered grid, sizing each
 * column to its widest cell and capping the whole thing at the terminal. It is
 * the same renderer a note field's grid uses, so a standalone table matches the
 * ones inside the panel. card() puts a grid inside a titled box when the two
 * belong together.
 *
 * Usage:
 *   php playground/18-output-table.php
 *
 *   # Off a TTY the grid stays, the colour goes:
 *   php playground/18-output-table.php 2>&1 | cat
 *
 *   # ASCII corners when the locale is not UTF-8:
 *   LC_ALL=C php playground/18-output-table.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
  });

$out = (new Tui($form))->output();

// A standalone grid: the columns size to their widest cell.
$out->table(['Item', 'Crates', 'Picked'], [
  ['Apricot', '4', 'Tuesday'],
  ['Peach', '2', 'Tuesday'],
  ['Carrot', '6', 'Wednesday'],
]);

$out->rule();

// The same grid inside a titled card, when the two belong together.
$out->card('Loaded for delivery', 'Everything below leaves the shed at seven.', ['Item', 'Crates'], [
  ['Apricot', '4'],
  ['Peach', '2'],
]);
