<?php

/**
 * @file
 * Inline editing: a field's editor opens in place on its panel row.
 *
 * Press Enter on a field and the editor appears where the value sits - the
 * confirm's Yes/No, the number's input, the select's option list - driven by
 * the field's own keys and collapsing back on accept or cancel. Inline is
 * the default for every field; ->standalone() opts a field out to a
 * full-screen editor, which suits large fields like the calendar's month
 * grid.
 *
 * Usage:
 *   php playground/04-inline-editing.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('options', 'Order options', function (PanelBuilder $p): void {
    $p->description('Press Enter on a field to edit it in place; Enter accepts, Esc cancels.');

    // Inline by default: the Yes/No toggle opens in the row.
    $p->confirm('organic', 'Organic only?')->default(FALSE);

    // The number's input opens in the row.
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);

    // The select drops its option list under the label, inside the panel.
    $p->select('ripeness', 'Ripeness')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
      'mixed' => 'Mixed',
    ]);

    // The month grid wants the whole screen: opt out of inline.
    $p->calendar('harvest', 'Harvest date')->standalone()->default('2026-07-15');
  });

try {
  $answers = (new Tui($form))->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The summary groups the answers by panel with provenance badges.
echo $answers->toSummary() . PHP_EOL;
