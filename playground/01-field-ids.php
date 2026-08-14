<?php

/**
 * @file
 * Field ids: derived from the label, or declared when they have to be exact.
 *
 * A block is declared by the label it draws, and the id it answers to is the
 * machine form of that label - so a question is written once. An id that has
 * to be a particular string - one a payload key, an environment variable or an
 * existing contract already fixed - is declared after the label.
 *
 * Usage:
 *   php playground/01-field-ids.php
 *
 *   # Unattended: the derived ids name the environment variables too, and
 *   # "sku" is the one field here that declared its own.
 *   PHPTUI_ORDER_NAME='Weekly Box' PHPTUI_SKU='PEAR-01' \
 *     php playground/01-field-ids.php < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Field ids')
  // The panel draws "Fresh produce" and answers to "fresh_produce"; a panel
  // declaring its own id states it between the title and the callback.
  ->panel('Fresh produce', function (PanelBuilder $p): void {
    // "Order name" collects into "order_name", read from PHPTUI_ORDER_NAME.
    $p->text('Order name')->default('Weekly Box')->required();

    // Punctuation and case are dropped on the way to the id, so this one
    // collects into "organic_only".
    $p->confirm('Organic only?')->default(TRUE);

    // A label already shaped like an id derives itself: "quantity".
    $p->number('quantity')->min(1)->max(99)->default(6);

    // An id after the label: the stock system calls this field "sku", so the
    // label is free to read as a question without changing what it answers to.
    $p->text('Stock code', 'sku')->default('PEAR-01');

    // A rule names the id, never the label - derived or declared, both are
    // just ids by the time a rule reads them.
    $p->text('Crate label')->derive(new Derive('{{sku}}-{{order_name}}', 'machine'));
  });

try {
  $answers = (new Tui($form))->run();
}
catch (InterruptException) {
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The ids the answers are keyed by - four derived from a label, one declared.
echo $answers->toJson() . PHP_EOL;
