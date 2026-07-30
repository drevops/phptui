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

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Password field')
  ->panel('main', 'Password', function (PanelBuilder $p): void {
    $p->password('code', 'Order code')->default('melon7')->revealable();
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
