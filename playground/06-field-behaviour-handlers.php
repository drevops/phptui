<?php

/**
 * @file
 * Handler classes: reusable per-field behaviour resolved by naming convention.
 *
 * Where closures.php declares behaviour inline on one field, a handler class
 * packages it for reuse: the facade searches the given namespaces for a class
 * named after the field id (order_code -> OrderCode) and uses its public
 * static validate() and transform() wherever the form declares no closure of
 * its own. The form stays declarative; the behaviour lives with the consumer.
 *
 * Usage:
 *   php playground/06-field-behaviour-handlers.php
 *
 *   # Unattended inputs run the same handler:
 *   PHPTUI_ORDER_CODE=PLUM99 php playground/06-field-behaviour-handlers.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the handler class loadable; a real consumer would
// autoload it from its own src/ instead.
require __DIR__ . '/handlers/OrderCode.php';

$form = Form::create('Handler classes')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    // No ->validate() or ->transform() here: both come from the OrderCode
    // class. Try accepting a code that is not six characters.
    $p->text('order_code', 'Order code')->default('MELON7');
    $p->text('note', 'Note')->default('First of the season');
  });

try {
  // The namespaces are searched in order for each field's handler class.
  $answers = (new Tui($form, handler_namespaces: ['Playground\\FieldBehaviour']))->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Any answered code passed the handler's validate() and was lowercased by
// its transform(); an untouched default is the form's own and stays as
// declared.
echo $answers->toSummary() . PHP_EOL;
