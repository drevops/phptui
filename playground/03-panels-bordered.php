<?php

/**
 * @file
 * Bordered panels: the whole panel browser wrapped in a border frame.
 *
 * The padded rounded box shown here is also the default look; this demo sets
 * it explicitly to name the options. The border is a theme display option
 * carrying a Border case - Rounded, Line, Double or None - alongside the
 * 'spacing' option, whose cases are Compact, Normal and Padded. Both are
 * closed sets, so an unknown value cannot be written in the first place. The
 * theme draws the hub, breadcrumb header, fields and key-hint footer inside
 * the frame, and every drilled-in sub-panel keeps it.
 *
 * Usage:
 *   php playground/03-panels-bordered.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Fruit basket')
  ->buttons(TRUE, 'Create', 'Cancel')
  ->panel('basics', 'Basics', function (PanelBuilder $p): void {
    $p->description('What the basket holds.');
    $p->text('name', 'Basket name')->default('weekly')->required();
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->default(6)->min(1)->max(99);
  })
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
    $p->description('How it arrives.');
    $p->select('method', 'Method')->default('pickup')->option('pickup', 'Pickup', 'At the stall')->option('doorstep', 'Doorstep', 'To your door');
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);

    // A nested sub-panel keeps the border on the drilled-in screen too.
    $p->panel('extras', 'Extras', function (PanelBuilder $sp): void {
      $sp->suggest('bag', 'Bag size')->default('Medium')->options([
        'Small' => 'Small',
        'Medium' => 'Medium',
        'Large' => 'Large',
      ]);
    });
  });

try {
  // Rounded and padded is the frame the documentation demos use; swap in
  // Border::Double, Border::Line or Border::None and Spacing::Normal or
  // Spacing::Compact to compare. clearOnExit(FALSE) keeps the final frame on
  // screen.
  $answers = (new Tui($form))
    ->theme('default', ['border' => Border::Rounded, 'spacing' => Spacing::Padded])
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
