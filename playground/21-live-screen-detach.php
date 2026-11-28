<?php

/**
 * @file
 * Live screen: leave the terminal to the shell and take it back later.
 *
 * Press Ctrl-Z to hand the terminal back to the shell. Type `bg` to let the
 * weighing carry on while you use the prompt, then `fg` to bring the screen
 * back. It redraws with every crate weighed while it was away. Typing `fg`
 * straight after Ctrl-Z brings it back at once.
 *
 * Usage:
 *   php playground/21-live-screen-detach.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$scale = new Markup('scale', 'Press Ctrl-Z, then bg, then fg.');

$form = Form::create('Weighing station')
  ->panel('Weighing', function (PanelBuilder $p): void {
    $p->select('Fruit')->options(['apple' => 'Apple', 'pear' => 'Pear', 'plum' => 'Plum'])->default('apple');
    $p->confirm('Organic only?')->default(TRUE);
  });

$tui = (new Tui($form))->place('header', $scale, tail: TRUE)->flow('header', Axis::Columns)->live(function (int $count) use ($scale): void {
  // Ticks keep arriving while the job runs in the background, so the count
  // shown on return includes them.
  $scale->body(sprintf('Crates weighed: %d - press Ctrl-Z to step away', intdiv($count, 2)));
}, 500);

try {
  echo $tui->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  exit(130);
}
