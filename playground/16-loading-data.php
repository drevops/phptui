<?php

/**
 * @file
 * Loading a panel's data on demand: an option loader and a panel preload.
 *
 * A field's ->options() and a panel's ->preload() take a callback instead of a
 * fixed value. Both run the first time the panel opens - the field shows a
 * themed "Loading…" until its callback returns - so a drill-in fetches only the
 * data that panel needs, and only once. Headless collection has no panel to
 * preload, so it resolves the option loaders up front and skips the preload.
 *
 * Usage:
 *   php playground/16-loading-data.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    // Prep the panel needs before its fields draw; runs once, interactive only.
    $p->preload(static function (): void {
      usleep(400000);
    });

    $p->text('name', 'Order name')->required();
    // A callback, not a fixed list: resolved when the panel opens, the field
    // showing "Loading…" until it returns.
    $p->select('fruit', 'Fruit')->options(static function (): array {
      usleep(400000);

      return ['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum', 'cherry' => 'Cherry'];
    });
  });

try {
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
