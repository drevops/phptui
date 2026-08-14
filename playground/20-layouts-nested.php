<?php

/**
 * @file
 * Arrangement all the way down, and the switch that draws every region's edge.
 *
 * Four arrangements stack in this one form, each nested inside a block the one
 * above it holds:
 *
 *   1. The screen runs MarketHallLayout - a two-row masthead flowing across, a
 *      scrolling body, a status bar flowing across. Its regions are named for
 *      what they are, so it states where the standard furniture goes.
 *   2. The panel in that body runs StallFloorLayout - two columns of unequal
 *      width, the wider one scrolling, the narrower one drawing its own box.
 *   3. The "Produce" panel in the wider column runs a grid, so its four
 *      sub-panels are drawn as windows onto what they hold rather than as rows
 *      saying what is behind them.
 *   4. The "Fruit" panel in the first window runs the shipped two-column
 *      layout, so its fields sit beside a standing note.
 *
 * Edges are one declaration wherever something occupies a rectangle - the
 * screen, a region, any block - and they name a style, a title and the sides
 * to draw. The noticeboard region draws all four with a title; the buttons
 * that end the form draw a top and a bottom and nothing else.
 *
 * A box spends the cells of whatever declared it: a row for each horizontal
 * edge, a column for each vertical one. So a region that draws all four holds
 * two fewer rows and two fewer columns than a bare one of the same size.
 *
 * Drive it with the arrow keys, drill in with Enter, and leave with Escape.
 *
 * Usage:
 *   php playground/20-layouts-nested.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Layout\LayoutManager;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Spacing;
use DrevOps\PhpTui\Tui;
use Playground\Layouts\MarketHallLayout;
use Playground\Layouts\StallFloorLayout;

require __DIR__ . '/../vendor/autoload.php';
// The requires make the classes loadable; a real consumer would autoload them.
require __DIR__ . '/layouts/MarketHallLayout.php';
require __DIR__ . '/layouts/StallFloorLayout.php';

LayoutManager::register('market-hall', MarketHallLayout::class);
LayoutManager::register('stall-floor', StallFloorLayout::class);

$form = Form::create('Market hall')
  ->panel('Hall', function (PanelBuilder $p): void {
    // Declared before anything is placed, so every block below knows the
    // regions it may go in.
    $p->layout('stall-floor');

    $p->in('stalls');
    $p->text('Order name')->default('Weekly Box');

    $p->panel('Produce', function (PanelBuilder $sp): void {
      // Two visual rows of two windows, with a row of the panel's own above
      // them and another below.
      $sp->layout(2, 2);

      $sp->text('Crates')->default('6');

      $sp->panel('Fruit', function (PanelBuilder $wp): void {
        // A shipped layout on a panel, reached by the same name the facade
        // reaches it by.
        $wp->layout('two-column');

        $wp->in('left');
        $wp->select('Fruit')->default('apple')->options([
          'apple' => 'Apple',
          'pear' => 'Pear',
          'plum' => 'Plum',
        ]);
        $wp->rating('Freshness')->default(4)->min(1)->max(5);

        $wp->in('right');
        $wp->markup('picked', 'Picked at dawn, graded at the bench.');
      });

      $sp->panel('Vegetables', function (PanelBuilder $wp): void {
        $wp->select('Vegetables')->multiple()->default(['carrot'])->options([
          'carrot' => 'Carrot',
          'leek' => 'Leek',
          'tomato' => 'Tomato',
        ]);
      });

      $sp->panel('Herbs', function (PanelBuilder $wp): void {
        $wp->confirm('Herb bundle?')->default(TRUE);
      });

      $sp->panel('Roots', function (PanelBuilder $wp): void {
        $wp->toggle('Washed')->default('yes')->options([
          'yes' => 'Yes',
          'no' => 'No',
        ]);
      });

      $sp->markup('scales', 'Crates are weighed at the bench.');
    });

    $p->panel('Delivery', function (PanelBuilder $sp): void {
      $sp->toggle('Slot')->default('morning')->options([
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
      ]);
      $sp->confirm('Gift wrap?')->default(FALSE);
    });

    // The cursor steps between the columns with the left and right keys, so the
    // noticeboard holds rows to land on rather than standing text alone.
    $p->in('noticeboard');
    $p->markup('hours', "Stalls open at six.\nScales close at four.");
    $p->toggle('Season')->default('summer')->options([
      'summer' => 'Summer',
      'autumn' => 'Autumn',
    ]);
    $p->confirm('Weekly list?')->default(TRUE);
  });

// Stacking the rows against each other keeps the frames about the arrangement
// rather than about the air the theme leaves between blocks, and leaves the
// two-row masthead room for both of its blocks.
$run = static function (string $said) use ($form): Answers {
  echo $said . PHP_EOL . PHP_EOL;

  return (new Tui($form))
    ->layout('market-hall')
    ->theme('default', ['border' => Border::Rounded, 'spacing' => Spacing::Normal])
    ->place('masthead', new Markup('season', '(summer season)'))
    ->place('statusbar', new Markup('version', 'v1.2.3'), tail: TRUE)
    ->clearOnExit(FALSE)
    ->run();
};

try {
  $answers = $run('Four arrangements nested in one form, two of them drawing their own edges.');
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Arranging exists only to draw, so the answers read as they would unarranged.
echo $answers->toSummary() . PHP_EOL;
