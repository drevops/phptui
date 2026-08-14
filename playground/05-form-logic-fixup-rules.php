<?php

/**
 * @file
 * Fix-up rules: reconciling dependent answers on every settle pass.
 *
 * A Fixup forces a field to a value while a condition holds - here, gift wrap
 * switches off whenever the delivery is not to the doorstep, whatever was
 * answered. Where ->when() hides a question, a fixup corrects an answer;
 * fix-ups run in the same settle loop as derivation and conditions, so the
 * result is consistent before the form ever returns.
 *
 * Usage:
 *   php playground/05-form-logic-fixup-rules.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Fixup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Fix-up rules')
  // Fix-ups are declared on the form, not on a field: each names the field
  // it sets, the value, and the condition under which it applies.
  ->fixup(new Fixup(set: 'gift_wrap', to: FALSE, when: new Condition('delivery', ne: 'doorstep')))
  ->panel('Delivery', function (PanelBuilder $p): void {
    $p->description('Pick a non-doorstep delivery and watch gift wrap reset.');
    $p->select('Delivery')->default('doorstep')->options([
      'pickup' => 'Pickup',
      'locker' => 'Locker',
      'doorstep' => 'Doorstep',
    ]);
    $p->confirm('Gift wrap?')->default(TRUE);
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

// The fixup already reconciled the result: with any non-doorstep delivery,
// gift wrap reads "no" here even when it was answered "yes".
echo $answers->toSummary() . PHP_EOL;
