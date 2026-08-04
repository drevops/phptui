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
 * The first three frames are the same screen and the same two blocks, and only
 * the region differs. Down a one-row header the markup has nowhere to go and
 * is clipped, because a region that was not declared to scroll clips what
 * outruns it. Across the same header both fit. Down a two-row header they
 * stack, which is what flowing down is for.
 *
 * A flow has two ends, and a block says which one it is packed from: ->add()
 * packs from the start of the axis, ->tail() from the end of it. The last two
 * frames put the key hints and a version string in one footer to show it - and
 * to show what happens where the two runs meet, which is that the head keeps
 * its space and the tail is cut.
 *
 * Usage:
 *   php playground/20-layouts-region-flow.php
 */

declare(strict_types=1);

use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;
use Playground\Layouts\MarketLayout;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the class loadable; a real consumer would autoload it.
require __DIR__ . '/layouts/MarketLayout.php';

// A layout is named rather than described inline, so the same one serves this
// script and 20-layouts-custom.php. Its header keeps two rows.
LayoutManager::register('market', MarketLayout::class);

$form = Form::create('Orchard')
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
    $p->text('courier', 'Courier')->default('Valley Runs');
    $p->number('weight', 'Basket weight')->default(1200)->min(200)->max(9000);
  });

// The blank row that normally shows between one block and the next is the
// theme's padding rather than the region's doing, and it would take a row of
// the header all by itself. Stacking the rows against each other keeps the
// frames about the flow.
$theme = new DefaultTheme(72, ['spacing' => Spacing::Normal]);
$panel = $form->root();

// Each frame gets a screen of its own: a region holds the blocks somebody put
// in it, so the same one cannot be flowed two ways at once.
$frame = static function (string $said, Axis $flow, string $layout) use ($panel, $theme): void {
  $screen = (new Assembler())->assemble($panel, $layout);

  // A region never knows which kind of block it was given, which is why markup
  // goes in beside a trail exactly as a field goes in beside a field.
  $screen->in('header')->flow($flow)->add(new Markup('preview', '(read-only preview)'));

  print $said . "\n\n";
  print (new ScreenRenderer($theme))->render($screen, 9, 72) . "\n\n";
};

// The footer already holds the key hints the assembler put there, so the
// version string joins them rather than arriving on an empty region.
$footer = static function (string $said, Axis $flow) use ($panel, $theme): void {
  $screen = (new Assembler())->assemble($panel, 'default');

  $screen->in('footer')->flow($flow)->tail(new Markup('version', 'v1.2.3'));

  print $said . "\n\n";
  print (new ScreenRenderer($theme))->render($screen, 9, 72) . "\n\n";
};

$frame('Down a one-row header: the markup is clipped, having nowhere to go.', Axis::Rows, 'default');
$frame('Across that same header: the trail and the markup share the one row.', Axis::Columns, 'default');
$frame('Down a two-row header: the markup stacks under the trail, as declared.', Axis::Rows, 'market');
$footer('Across the footer: the hints keep the left, the version the right.', Axis::Columns);
$footer('Down that same footer: one row, so the head keeps it and the tail is cut.', Axis::Rows);
