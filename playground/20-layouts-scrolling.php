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
 * The frames below are one grid of four windows, first with the room it comes
 * to and then in a frame too short for it. The overflow mark says which edge
 * the rest is past, and moving the grid carries the panel's own rows with it -
 * so nothing is dropped quietly and the pair around the windows is as much of
 * the surface as the windows are.
 *
 * Usage:
 *   php playground/20-layouts-scrolling.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;

require __DIR__ . '/../vendor/autoload.php';

// Two visual rows of two windows, with a row of the form's own above them and
// another below: the pair around the windows travels with the grid, because a
// surface that left them behind would not be one surface.
$form = Form::create('Market stall')
  ->buttons(FALSE)
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->layout(2, 2);

    $p->text('name', 'Order name')->default('Weekly Box');

    $p->panel('fruit', 'Fruit', function (PanelBuilder $sp): void {
      $sp->select('fruit', 'Fruit')->default('apple')->options([
        'apple' => 'Apple',
        'pear' => 'Pear',
        'plum' => 'Plum',
      ]);
    });
    $p->panel('veg', 'Vegetables', function (PanelBuilder $sp): void {
      $sp->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
        'carrot' => 'Carrot',
        'leek' => 'Leek',
        'tomato' => 'Tomato',
      ]);
    });
    $p->panel('herbs', 'Herbs', function (PanelBuilder $sp): void {
      $sp->confirm('bundle', 'Herb bundle?')->default(TRUE);
    });
    $p->panel('delivery', 'Delivery', function (PanelBuilder $sp): void {
      $sp->toggle('slot', 'Slot')->default('morning')->options([
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
      ]);
    });

    $p->markup('bench', 'Crates are weighed at the bench.');
  });

// Stacking the rows against each other keeps the frames about the movement
// rather than about the air the theme leaves between blocks.
$theme = new DefaultTheme(72, ['spacing' => Spacing::Normal]);
$panel = $form->root();

// The panel the grid arranges has to be the one on screen for the grid to be
// what is drawn, which is what going into it means.
$order = $panel->children()[0]->enter();

// A grid is the shipped arrangement that scrolls, and the offset is its own -
// there is no region here to ask for it.
$grid = $order->currentLayout();

$frame = static function (string $said, int $rows) use ($panel, $theme): void {
  $screen = (new Assembler())->assemble($panel, 'default');

  print $said . "\n\n";
  print (new ScreenRenderer($theme))->render($screen, $rows, 72) . "\n\n";
};

$frame('The whole grid, in a frame with the room for it.', 16);
$frame('The same grid in nine rows: the mark says the rest is below.', 9);

$grid->scrollTo(3);
$frame('Moved down three rows: every line moved, the note among them.', 9);
