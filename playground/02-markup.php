<?php

/**
 * @file
 * Markup: formatted content in the form flow that collects nothing.
 *
 * Markup renders a title and body inline in the form. The cursor skips it, it
 * never appears in the answers, and headless runs omit it entirely. Its text
 * takes the same `{{field}}` templating derived values use, so markup can
 * reflect earlier answers; `->border()` frames it as a card. How it is laid
 * out is a presentation choice over one block: `markup()` writes the body
 * first, `note()` the title first, and both build the same thing.
 *
 * Usage:
 *   php playground/02-markup.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Markup')
  ->panel('Order', function (PanelBuilder $p): void {
    // Prose: bare lines in the flow, written body first.
    $p->markup('Every crate is weighed at the packing bench.');
    $p->note('Fresh produce order')->body('This card is read-only - the cursor skips it and it collects nothing.');
    $p->text('Item')->default('Pear');
    $p->note('Ready to pack')->body('Packing {{item}} into the basket.')->border();
  });

try {
  // Interactive on a terminal; headless otherwise - either way the markup is
  // absent from the JSON, which carries only the item.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
