<?php

/**
 * @file
 * Nested panels: a hub with drill-in sub-panels, nested to any depth.
 *
 * The root screen is a hub listing the panels; Enter drills into one, Escape
 * backs out, and a sub-panel row shows a live summary of its answers. Panels
 * carry descriptions, options carry per-option descriptions, and the form's
 * submit/cancel buttons take custom labels.
 *
 * Usage:
 *   php playground/03-panels-nested.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  // Custom button labels; the buttons render on the root hub only.
  ->buttons(TRUE, 'Save', 'Discard')
  ->panel('identity', 'Order', function (PanelBuilder $p): void {
    // A panel description renders under the panel title.
    $p->description('Who this order is for.');
    $p->text('name', 'Order name')->default('Weekly')->required();
    // Derived from another answer; see playground/05-form-logic-*.
    $p->text('slug', 'Slug')->description('Derived from the order name.')->derive(new Derive('{{name}}', 'machine'));
  })
  ->panel('shipping', 'Delivery', function (PanelBuilder $p): void {
    $p->description('How it arrives.');
    // Options declared one by one carry their own descriptions.
    $p->select('delivery', 'Delivery')->default('pickup')->option('pickup', 'Pickup', 'At the stall')->option('locker', 'Locker', 'Nearby locker')->option('doorstep', 'Doorstep', 'To your door');
    $p->confirm('gift', 'Gift wrap?')->default(TRUE);

    // A nested sub-panel: rendered as a drillable row with a value summary.
    $p->panel('extras', 'Extras', function (PanelBuilder $sp): void {
      $sp->description('Optional add-ons.');
      $sp->select('addons', 'Add-ons')->multiple()->options([
        'herbs' => 'Herbs',
        'nuts' => 'Nuts',
        'seeds' => 'Seeds',
      ]);
      // Conditional visibility; see playground/05-form-logic-*.
      $sp->text('herb_note', 'Herb note')->default('mixed')->when(new Condition('addons', contains: 'herbs'));

      // Sub-panels nest to any depth.
      $sp->panel('packaging', 'Packaging', function (PanelBuilder $tp): void {
        $tp->suggest('weight', 'Bag weight')->default('250g')->options([
          '250g' => '250g',
          '500g' => '500g',
          '1kg' => '1kg',
        ]);
      });
    });
  });

try {
  // The rounded border frames the hub and every drilled-in screen; keep the
  // final frame on screen after the TUI exits instead of clearing it.
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
