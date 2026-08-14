<?php

/**
 * @file
 * Modal panels: a panel that opens as a dialog over its dimmed parent.
 *
 * A panel marked ->modal() is not drilled into like an ordinary sub-panel; it
 * opens as a bordered box centered over the dimmed parent and is dismissed
 * through its own submit/cancel buttons. Submit keeps the edits; cancel or
 * Escape restores the answers exactly as they were when the dialog opened. A
 * modal with no fields is a plain message to acknowledge.
 *
 * Usage:
 *   php playground/03-panels-modal.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('Basket', function (PanelBuilder $p): void {
    $p->description('Your produce selection.');
    $p->text('Item')->default('Pear');
    $p->number('Quantity')->default(6)->min(1)->max(99);
    $p->select('Ripeness')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);

    // A modal that collects fields: activating it opens a centered dialog
    // over the dimmed basket. Save keeps the edits; Discard (or Escape)
    // restores the fields exactly as they were when the dialog opened.
    $p->panel('Gift options', function (PanelBuilder $m): void {
      $m->modal('Save', 'Discard');
      $m->description('Wrap this order as a gift.');
      $m->confirm('Gift wrap?')->default(TRUE);
      $m->text('Gift message')->default('Enjoy the harvest');
    });

    // A text-only modal: a warning the reader acknowledges. With no fields,
    // the dialog is the whole message, and either button dismisses it.
    $p->panel('Empty the basket', function (PanelBuilder $m): void {
      $m->modal('Empty it', 'Keep it');
      $m->description('This clears every item from your basket.' . chr(10) . 'There is no undo.');
    });
  });

try {
  // Keep the final frame on screen after the TUI exits.
  $answers = (new Tui($form))
    ->theme('default', ['border' => Border::Rounded])
    ->clearOnExit(FALSE)
    ->run();
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
