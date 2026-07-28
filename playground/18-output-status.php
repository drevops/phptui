<?php

/**
 * @file
 * Output as status lines: the running commentary between steps.
 *
 * The five status kinds - note, info, success, warning and error - each lead
 * with their own glyph and carry their own colour from the active theme, so
 * they stay distinguishable with colour off and in ASCII alike. Pass a
 * Status case to status() to pick the kind at runtime.
 *
 * Usage:
 *   php playground/18-output-status.php
 *
 *   # Off a TTY the glyphs stay, the colour goes:
 *   php playground/18-output-status.php 2>&1 | cat
 *
 *   # ASCII glyphs when the locale is not UTF-8:
 *   LC_ALL=C php playground/18-output-status.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
  });

$out = (new Tui($form))->output();

// The five kinds, each with its own glyph and colour. The calls chain.
$out->info('Checking the morning harvest')
  ->success('Apricots picked and weighed')
  ->warning('Only two crates of pears left')
  ->error('The cherry shelf is empty')
  ->note('Anything short is refunded, never substituted');

// The kind can also be chosen at runtime.
foreach ([Status::Success, Status::Warning] as $status) {
  $out->status($status, 'Packed to the ' . $status->value . ' shelf');
}
