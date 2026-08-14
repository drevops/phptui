<?php

/**
 * @file
 * Scrolling an arrangement: every line of a grid moving together as one.
 *
 * Scrolling is normally a region's own doing - it is handed one number and
 * decides what of its blocks is in sight. A grid cannot work that way. Its
 * windows sit beside each other, so moving one of them down while its
 * neighbour stays put would tear the visual row in half, and no window can move
 * its siblings any more than it can size them. So the offset belongs to the
 * arrangement: a grid declares itself a surface, is drawn whole, and shows what
 * its space has room for.
 *
 * The form below is one grid of four windows, with a row of the form's own
 * above them and another below, in a frame deliberately too short for it. Move
 * with the arrow keys and the whole surface travels: the overflow mark says
 * which edge the rest is past, and the pair around the windows moves with them,
 * because a surface that left them behind would not be one surface.
 *
 * The box is what makes that legible - it says where the frame ends, so what
 * the grid has run past can be seen rather than inferred.
 *
 * Usage:
 *   php playground/20-layouts-scrolling.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Spacing;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// Three visual rows of two windows, with a row of the form's own above them and
// another below: the pair around the windows travels with the grid, because a
// surface that left them behind would not be one surface.
$form = Form::create('Market stall')
  ->panel('Order', function (PanelBuilder $p): void {
    $p->layout(2, 2, 2);

    $p->text('Order name')->default('Weekly Box');

    $p->panel('Fruit', function (PanelBuilder $sp): void {
      $sp->select('Fruit')->default('apple')->options([
        'apple' => 'Apple',
        'pear' => 'Pear',
        'plum' => 'Plum',
      ]);
    });
    $p->panel('Vegetables', function (PanelBuilder $sp): void {
      $sp->select('Vegetables')->multiple()->default(['carrot'])->options([
        'carrot' => 'Carrot',
        'leek' => 'Leek',
        'tomato' => 'Tomato',
      ]);
    });
    $p->panel('Herbs', function (PanelBuilder $sp): void {
      $sp->confirm('Herb bundle?')->default(TRUE);
    });
    $p->panel('Delivery', function (PanelBuilder $sp): void {
      $sp->toggle('Slot')->default('morning')->options([
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
      ]);
    });
    $p->panel('Ripeness', function (PanelBuilder $sp): void {
      $sp->toggle('Ripeness')->default('ripe')->options([
        'ripe' => 'Ripe',
        'unripe' => 'Unripe',
      ]);
    });
    $p->panel('Freshness', function (PanelBuilder $sp): void {
      $sp->rating('Freshness')->default(4)->min(1)->max(5);
    });

    $p->markup('Crates are weighed at the bench.');
  });

try {
  // A capped height is what makes the movement the point: the grid comes to
  // more rows than the frame keeps however tall the terminal is, so there is
  // always somewhere for it to travel. Stacking the rows against each other
  // keeps the frame about the movement rather than about the air the theme
  // leaves between blocks.
  $answers = (new Tui($form))
    ->theme('default', ['border' => Border::Rounded, 'spacing' => Spacing::Normal, 'max_height' => 11])
    ->clearOnExit(FALSE)
    ->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The offset arranges drawing and nothing else, so the answers read exactly as
// they would in a frame with room for the whole grid.
echo $answers->toSummary() . PHP_EOL;
