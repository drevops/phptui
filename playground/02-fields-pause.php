<?php

/**
 * @file
 * Pause field: an acknowledgement gate with no value.
 *
 * Enter or Space accepts and the form moves on - useful before a consequential
 * step. Unattended runs auto-acknowledge it, so a pause never blocks
 * automation.
 *
 * Usage:
 *   php playground/02-fields-pause.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Pause field')
  ->panel('main', 'Pause', function (PanelBuilder $p): void {
    $p->pause('review', 'Review your basket');
  });

try {
  // Interactive on a terminal; auto-acknowledged when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
