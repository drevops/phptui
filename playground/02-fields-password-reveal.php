<?php

/**
 * @file
 * Password field with ->revealable(): a Tab-toggled plaintext peek.
 *
 * The value renders masked as usual; Tab flips the editor to plaintext and
 * back, for checking a typed secret before accepting it. The collected value
 * is identical either way.
 *
 * Usage:
 *   php playground/02-fields-password-reveal.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Password field')
  ->panel('Password', function (PanelBuilder $p): void {
    $p->password('Order code')->default('melon7')->revealable();
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
