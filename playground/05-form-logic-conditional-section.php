<?php

/**
 * @file
 * Conditional sections: a whole panel that comes and goes on an answer.
 *
 * ->when() on a panel reads exactly as it does on a field, and takes everything
 * the panel holds with it: while the condition does not hold the section is not
 * on the hub, its questions are not asked, not drawn and not in the answers -
 * interactively and headlessly alike. That is what saves a form from declaring
 * the same condition on every field of a group.
 *
 * Answer "Gift wrap?" with yes and the Gift wrapping section joins the hub;
 * answer no and it is gone, ribbon and message with it.
 *
 * Usage:
 *   php playground/05-form-logic-conditional-section.php
 *   PHPTUI_GIFT=1 php playground/05-form-logic-conditional-section.php \
 *     < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Conditional sections')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->description('The gift answer decides whether the next section exists at all.');
    $p->text('name', 'Order name')->default('Weekly Box')->required();
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);
  })
  ->panel('wrapping', 'Gift wrapping', function (PanelBuilder $p): void {
    // The condition sits on the section, so neither field repeats it.
    $p->when(new Condition('gift', eq: TRUE));
    $p->select('ribbon', 'Ribbon')->default('cherry')->options([
      'cherry' => 'Cherry red',
      'melon' => 'Melon green',
      'plum' => 'Plum purple',
    ]);
    $p->text('message', 'Card message')->default('Enjoy the harvest');
  })
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
    $p->toggle('slot', 'Delivery slot')->default('morning')->options([
      'morning' => 'Morning',
      'afternoon' => 'Afternoon',
    ]);
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

// A hidden section contributes no answers: with the gift answer off, neither
// the ribbon nor the message is in the summary.
echo $answers->toSummary() . PHP_EOL;
