<?php

/**
 * @file
 * Password field: text rendered as a mask everywhere it appears.
 *
 * The editor, the field row and the summary all show a mask; the accepted
 * value stays plain for the consumer. Add ->revealable() for a Tab-toggled
 * plaintext peek (see password-reveal.php) and ->confirmation() to ask for
 * the value twice and reject a mismatch.
 *
 * Usage:
 *   php playground/02-fields-password.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Password field')
  ->panel('main', 'Password', function (PanelBuilder $p): void {
    $p->password('code', 'Order code')->default('melon7');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
