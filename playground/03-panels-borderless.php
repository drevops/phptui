<?php

/**
 * @file
 * Borderless panels: the same form as bordered.php, without the frame.
 *
 * The default look is a padded rounded box; an explicit Border::None and
 * Spacing::Normal strip it back to bare rows. Run this next to bordered.php to
 * compare the two looks; the form, fields and keys are identical.
 *
 * Usage:
 *   php playground/03-panels-borderless.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Spacing;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Fruit basket')
  ->buttons(TRUE, 'Create', 'Cancel')
  ->panel('Basics', function (PanelBuilder $p): void {
    $p->description('What the basket holds.');
    $p->text('Basket name')->default('weekly')->required();
    $p->select('Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('Quantity')->default(6)->min(1)->max(99);
  })
  ->panel('Delivery', function (PanelBuilder $p): void {
    $p->description('How it arrives.');
    $p->select('Method')->default('pickup')->option('pickup', 'Pickup', 'At the stall')->option('doorstep', 'Doorstep', 'To your door');
    $p->confirm('Gift wrap?')->default(FALSE);

    $p->panel('Extras', function (PanelBuilder $sp): void {
      $sp->suggest('Bag size')->default('Medium')->options([
        'Small' => 'Small',
        'Medium' => 'Medium',
        'Large' => 'Large',
      ]);
    });
  });

try {
  // The default look is a padded rounded box; this demo opts out of both
  // explicitly to show the bare, frameless rendering.
  $answers = (new Tui($form))->theme('default', ['border' => Border::None, 'spacing' => Spacing::Normal])->clearOnExit(FALSE)->run();
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
