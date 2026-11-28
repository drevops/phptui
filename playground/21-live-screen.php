<?php

/**
 * @file
 * Live screen: a form that redraws on a timer, with no key pressed.
 *
 * `->live()` makes the screen wait for a key for a fixed interval and run a
 * tick callback each time the wait times out. The tick changes a block it
 * holds - here a card counting the crates weighed at the bench - and the
 * screen redraws when the frame changed. Resizing the terminal redraws the
 * form at the new size. The fields keep answering keys between ticks.
 *
 * Usage:
 *   php playground/21-live-screen.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$crates = ['Apple', 'Pear', 'Plum', 'Cherry', 'Peach', 'Melon'];
$scale = new Markup('scale', 'Waiting for the first crate...');

$form = Form::create('Weighing station')
  ->panel('Weighing', function (PanelBuilder $p): void {
    $p->select('Fruit')->options(['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry'])->default('apple');
    $p->confirm('Organic only?')->default(TRUE);
  });

// Placed beside the trail, so the line stays in sight on every panel.
$tui = (new Tui($form))->place('header', $scale, tail: TRUE)->flow('header', Axis::Columns)->live(function (int $count) use ($scale, $crates): void {
  // Every tick weighs one more crate, cycling through the fruit on the bench.
  $fruit = $crates[($count - 1) % count($crates)];
  $grams = 1200 + (($count * 137) % 900);

  $scale->body(sprintf('Crates weighed: %d - last %s, %d g', $count, $fruit, $grams));
}, 500);

try {
  // Interactive on a terminal; headless otherwise, where nothing ticks.
  echo $tui->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
