<?php

/**
 * @file
 * Indented conditional fields: the dependency chain read as a hierarchy.
 *
 * The 'indent_conditional' theme option steps a ->when() field in from the
 * fields that decide it - one step per condition in the chain, so a field
 * shown behind three conditions sits three steps in. Off by default: without
 * it every field renders flush, as the other form-logic demos show. Toggle the
 * answers from the top down and watch the steps appear.
 *
 * Usage:
 *   php playground/05-form-logic-conditional-indent.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Conditional indentation')
  ->panel('Produce order', function (PanelBuilder $p): void {
    $p->description('Pick Vegetable, then add Carrot, to step the chain open.');
    $p->select('Category')->default('fruit')->options([
      'fruit' => 'Fruit',
      'vegetable' => 'Vegetable',
      'herb' => 'Herb',
    ]);

    // One condition deep: it hangs off the unconditional category above.
    $p->select('Basket')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'potato' => 'Potato',
      'tomato' => 'Tomato',
    ])->when(new Condition('category', eq: 'vegetable'));

    // Two deep: its rule names a field that is itself conditional.
    $p->confirm('Weekly delivery?')->default(TRUE)->when(new Condition('basket', contains: 'carrot'));

    // Three deep, and the steps keep going for as long as the chain does.
    $p->text('Courier note')->default('Leave at the gate')->when(new Condition('weekly_delivery', eq: TRUE));

    // Unconditional again, so the rows return to the frame edge.
    $p->number('Quantity')->min(1)->max(99)->default(6);
  });

try {
  $answers = (new Tui($form))->theme('default', ['indent_conditional' => TRUE])->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

echo $answers->toSummary() . PHP_EOL;
