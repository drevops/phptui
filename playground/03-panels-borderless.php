<?php

/**
 * @file
 * Borderless panels: the same form as bordered.php, without the frame.
 *
 * The default look is a padded rounded box; the explicit 'none' border and
 * 'normal' spacing strip it back to bare rows. Run this next to bordered.php to
 * compare the two looks; the form, fields and keys are identical.
 *
 * Usage:
 *   php playground/03-panels-borderless.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
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

    $p->panel('extras', 'Extras', function (PanelBuilder $sp): void {
      $sp->suggest('bag', 'Bag size')->default('Medium')->options([
        'Small' => 'Small',
        'Medium' => 'Medium',
        'Large' => 'Large',
      ]);
    });
  });

try {
  // The default look is a padded rounded box; this demo opts out of both
  // explicitly to show the bare, frameless rendering.
  $answers = (new Tui($form))->theme('default', ['border' => 'none', 'spacing' => 'normal'])->clearOnExit(FALSE)->run();
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
