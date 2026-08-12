<?php

/**
 * @file
 * Rating field: a graded answer picked from a scale, accepted as an int.
 *
 * Left and Right (or Up and Down) walk the scale a point at a time and stop at
 * either end, and a digit jumps straight to its point. Where number takes a
 * typed figure from an open range, rating picks one of a handful of graded
 * points - and each point can carry a caption naming what it means.
 *
 * Usage:
 *   php playground/02-fields-rating.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Rating field')
  ->panel('main', 'Rating', function (PanelBuilder $p): void {
    // The ends default to one and five; captioning them names the scale without
    // claiming a reading for every step in between.
    $p->rating('freshness', 'Freshness')->default(4)->captions([
      1 => 'Poor',
      3 => 'Fair',
      5 => 'Excellent',
    ]);
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
