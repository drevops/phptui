<?php

/**
 * @file
 * Panel layouts: sub-panels arranged as a grid of side-by-side columns.
 *
 * The grid is the one arrangement built from a shape rather than picked by a
 * name, and ->layout() takes either. Counts are the shape: each argument is one
 * visual row naming how many panels sit beside each other in it, filled in
 * declaration order. layout(1, 2) puts the first panel alone on top and the
 * next two side by side below; layout(2, 2) makes four windows. Each window is
 * a region of the grid, so a panel is drawn in the one that names it.
 *
 * A grid keeps a region either side of its windows for the panel's own rows,
 * and which of the pair a row is in is the whole of where it sits: written
 * before the first window it draws over the grid, written after the last it
 * draws under it. Produce shows both - a crate count above its two windows and
 * a standing note below them - and neither region is ever named here, because
 * where a row was written is what says which one it went in.
 *
 * A name carries no shape, so no name reaches a grid: a panel that declares no
 * arrangement lists its sub-panels as rows instead - which is what Delivery
 * does here, beside Produce's layout(2) of Fruit and Vegetables. Every level
 * declares its own. The arrows move spatially across the grid, and a slot count
 * that does not match the panels throws when the form is built.
 *
 * Usage:
 *   php playground/03-panels-layout.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Market stall')
  ->layout(1, 2)
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('Summary', function (PanelBuilder $p): void {
    $p->description('The order at a glance.');
    $p->text('Order name')->default('Weekly Box')->required();
  })
  ->panel('Produce', function (PanelBuilder $p): void {
    // Counts: two sub-panels share one visual row.
    $p->layout(2);

    // Written before the first window, so it goes in the region above them.
    $p->number('Crates')->default(6)->min(1)->max(99);

    $p->panel('Fruit', function (PanelBuilder $sp): void {
      $sp->select('Fruit')->default('apple')->options([
        'apple' => 'Apple',
        'banana' => 'Banana',
        'cherry' => 'Cherry',
      ]);
    });
    $p->panel('Vegetables', function (PanelBuilder $sp): void {
      $sp->select('Vegetables')->multiple()->default(['carrot'])->options([
        'carrot' => 'Carrot',
        'tomato' => 'Tomato',
        'spinach' => 'Spinach',
      ]);
    });

    // Written after the last window, so it goes in the region below them.
    $p->markup('bench', 'Crates are weighed at the bench.');
  })
  ->panel('Delivery', function (PanelBuilder $p): void {
    // No shape, so no grid: the two sub-panels are rows you select, one under
    // the other, which is what a panel does without being told otherwise.
    $p->panel('When', function (PanelBuilder $sp): void {
      $sp->toggle('Slot')->default('morning')->options([
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
      ]);
    });
    $p->panel('Wrapping', function (PanelBuilder $sp): void {
      $sp->confirm('Gift wrap?')->default(FALSE);
    });
  });

try {
  // Swap layout(1, 2) above for layout(3), layout(2, 1) or - with a fourth
  // panel - layout(2, 2) to compare the arrangements.
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
