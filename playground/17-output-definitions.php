<?php

/**
 * @file
 * Output as a definition list: label and value pairs, aligned.
 *
 * The definitions() call lays label/value pairs into two columns - the labels
 * sized to the widest of them, the values wrapped underneath their own column
 * - and styles each with the active theme's atoms. It is the summary a
 * consumer prints once a form is collected, so the answers read back in the
 * same palette they were entered in.
 *
 * Usage:
 *   php playground/17-output-definitions.php
 *
 *   # Off a TTY the alignment stays, the colour goes:
 *   php playground/17-output-definitions.php 2>&1 | cat
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

$out->box('Your order is packed.', 'Summary');

// The labels size to the widest of them; a long value wraps under its column.
$out->definitions([
  'Order' => 'Summer Box',
  'Fruit' => 'Apricot, Peach, Plum',
  'Vegetables' => 'Carrot, Spinach, Tomato',
  'Quantity' => '6 baskets',
  'Note' => 'Everything is picked the morning it ships, packed in the shed, and loaded onto the van before seven.',
]);

$out->success('Leaving the packing shed at 07:00');
