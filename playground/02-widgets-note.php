<?php

/**
 * @file
 * Note field: a non-interactive card that shows context but collects nothing.
 *
 * A note renders a title and body inline in the form flow. The cursor skips it,
 * it never appears in the answers, and headless runs omit it entirely. Its text
 * takes the same `{{field}}` templating derived values use, so a note can
 * reflect earlier answers; `->border()` frames the card. Here the summary note
 * echoes the item, and the collected JSON carries only the field's value.
 *
 * Usage:
 *   php playground/02-widgets-note.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Note field')
  ->panel('main', 'Order', function (PanelBuilder $p): void {
    $p->note('intro', 'Fresh produce order')->description('This card is read-only - the cursor skips it and it collects nothing.');
    $p->text('item', 'Item')->default('Pear');
    $p->note('summary', 'Ready to pack')->description('Packing {{item}} into the basket.')->border();
  });

try {
  // Interactive on a terminal; headless otherwise - either way the notes are
  // absent from the JSON, which carries only the item.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
