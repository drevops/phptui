<?php

/**
 * @file
 * Region flow: two blocks side by side in one region, or one under the other.
 *
 * A region runs the blocks inside it in one direction - down it by default,
 * across it when it is told to. Flowing across is what saves a form from
 * nesting a layout every time two things belong on the same line: a trail and
 * a standing block of markup in one header is a flow, not a second
 * arrangement.
 *
 * The regions around the form are the session's rather than the form's, so
 * what stands in them is stated on the facade: ->place() puts a block in a
 * named region, and ->flow() turns that region across. The standard furniture
 * goes in first, so a placed block lands after the trail or the key hints its
 * region already holds.
 *
 * A flow has two ends, and a block says which one it is packed from: ->place()
 * packs from the start of the axis, its "tail" argument from the end. Where
 * the two runs meet, the head keeps its space and the tail is cut.
 *
 * Two sessions run one after the other, the same form and the same two placed
 * blocks in both, and only the regions differ. Drive each with the arrow keys
 * and leave it with Escape; the frames stay on screen to be compared.
 *
 * Usage:
 *   php playground/20-layouts-region-flow.php
 */

declare(strict_types=1);

use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Tui;
use Playground\Layouts\MarketLayout;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the class loadable; a real consumer would autoload it.
require __DIR__ . '/layouts/MarketLayout.php';

// A layout is named rather than described inline, so the same one serves this
// script and 20-layouts-custom.php. Its header keeps two rows.
LayoutManager::register('market', MarketLayout::class);

// Each session gets a form of its own, so the second opens on the answers the
// form declares rather than on whatever the first was left holding.
$form = static fn (): Form => Form::create('Orchard')
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
    $p->text('courier', 'Courier')->default('Valley Runs');
    $p->number('weight', 'Basket weight')->default(1200)->min(200)->max(9000);
  });

// The box is what makes the regions legible: it says where the frame ends, so
// the rows a header keeps and the row a footer keeps can be counted off it.
// The blank row that normally shows between one block and the next is the
// theme's padding rather than the region's doing, and it would take a row of
// the two-row header all by itself - so the rows are stacked against each
// other and the frames stay about the flow.
$run = static function (string $said, string $layout, Axis $flow) use ($form): void {
  echo $said . PHP_EOL . PHP_EOL;

  (new Tui($form()))
    ->layout($layout)
    ->theme('default', ['border' => Border::Rounded, 'spacing' => Spacing::Normal])
    ->place('header', new Markup('preview', '(read-only preview)'))
    ->place('footer', new Markup('version', 'v1.2.3'), tail: TRUE)
    ->flow('header', $flow)
    ->flow('footer', $flow)
    ->clearOnExit(FALSE)
    ->run();

  echo PHP_EOL;
};

try {
  $run('Across: the note shares the header row, and the version the footer row.', 'default', Axis::Columns);
  // A region never knows which kind of block it was given, so the note stacks
  // under the trail exactly as a field stacks under a field - given the rows.
  $run('Down: the note stacks under the trail, and the one-row footer cuts the version.', 'market', Axis::Rows);
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Regions arrange drawing and nothing else, so the answers read exactly as
// they would under any other arrangement.
echo 'Both sessions collected the same two answers.' . PHP_EOL;
