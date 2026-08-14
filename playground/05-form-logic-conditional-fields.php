<?php

/**
 * @file
 * Conditional fields: visibility driven by other answers.
 *
 * ->when() attaches a condition to a field; the field only renders - and only
 * collects - while the condition holds. A leaf Condition names a field and
 * one operator (eq, ne, in, contains; none tests truthiness), and conditions
 * compose with Condition::all(), any() and not(). Visibility re-settles with
 * every answer change, interactively and headlessly alike.
 *
 * Usage:
 *   php playground/05-form-logic-conditional-fields.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Conditional fields')
  ->panel('Packing', function (PanelBuilder $p): void {
    $p->description('Toggle the answers and watch fields appear.');
    $p->select('Contents')->multiple()->default(['fruit'])->options([
      'fruit' => 'Fruit',
      'veg' => 'Vegetables',
      'herbs' => 'Herbs',
    ]);
    $p->select('Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);

    // Shown only while "herbs" is among the selected contents.
    $p->text('Herb bundle')->default('mixed')->when(new Condition('contents', contains: 'herbs'));

    // A composite: herbs selected AND the large box. any() and not() compose
    // the same way, to any depth.
    $p->confirm('Weekly herb delivery?')->default(TRUE)->when(Condition::all(new Condition('contents', contains: 'herbs'), new Condition('box_size', eq: 'large')));

    // An operator over a set: shown for the small or medium box.
    $p->confirm('Stack the boxes?')->default(FALSE)->when(new Condition('box_size', in: ['small', 'medium']));
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

// A hidden field contributes no answer: flip "contents" away from herbs and
// "herb_bundle" disappears from the summary too.
echo $answers->toSummary() . PHP_EOL;
