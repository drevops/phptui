<?php

/**
 * @file
 * Element overrides: restating a handful of glyphs without a theme class.
 *
 * Subclassing a theme is the full answer and overkill when all that differs is
 * a mark or two. Handing ->theme() a closure instead of a name gives it a
 * ThemeBuilder, and the overrides are grouped by the block that declares them -
 * so the block's prefix is implied, and ->separator() means one thing under
 * ->breadcrumb() and another under ->legend().
 *
 * Three kinds of thing can be restated. A glyph takes two arguments, the mark
 * and its ASCII stand-in, so a patch cannot leave one display mode working and
 * the other broken. Text takes one, because a phrase the reader parses is not
 * something a terminal fails to draw. A colour takes the palette parts in
 * order.
 *
 * It is a patch, not a replacement: every element nobody names keeps the
 * selected theme's own answer, which is why the unpicked options below still
 * carry the mark the theme draws for them.
 *
 * Usage:
 *   php playground/09-themes-elements.php
 *
 *   # The ASCII stand-ins, on any terminal:
 *   LC_ALL=C php playground/09-themes-elements.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Override\BreadcrumbOverrides;
use DrevOps\Tui\Theme\Override\FieldOverrides;
use DrevOps\Tui\Theme\Override\LegendOverrides;
use DrevOps\Tui\Theme\Sgr;
use DrevOps\Tui\Theme\ThemeBuilder;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Element overrides')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->panel('basket', 'Basket', function (PanelBuilder $sp): void {
      // Two segments in the trail, so the breadcrumb has a separator to draw.
      $sp->select('produce', 'Produce')->multiple()->default(['apple', 'carrot'])->options([
        'apple' => 'Apple',
        'carrot' => 'Carrot',
        'tomato' => 'Tomato',
      ]);
    });
    $p->text('name', 'Order name')->default('Weekly')->help('Anything you will recognise on the crate.');
  });

try {
  // The name picks the theme; the closure states what that theme draws
  // differently. Everything it does not name is left exactly as it was.
  $answers = (new Tui($form))
    ->theme('default')
    ->theme(static fn(ThemeBuilder $t): ThemeBuilder => $t
      ->breadcrumb(static fn(BreadcrumbOverrides $b): BreadcrumbOverrides => $b
        ->separator('»', '->'))
      ->legend(static fn(LegendOverrides $l): LegendOverrides => $l
        ->separator('•', '|')
        ->key(Sgr::Bold, Sgr::BrightCyan))
      ->field(static fn(FieldOverrides $f): FieldOverrides => $f
        // The mark saying which row has the cursor, and which option inside an
        // open one has it - two different marks, so two calls.
        ->selector('▶', '=>')
        ->optionSelector('▸', '->')
        // The mark an option carries once it is picked.
        ->optionMarker('▣', '[x]')
        // The mark showing where the next keystroke lands.
        ->caret('▎', '|')
        // Text rather than a glyph: one argument, no stand-in to state.
        ->valueSeparator(' / ')))
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

// Elements are how a block is drawn, so the answers are untouched by any of it.
echo $answers->toSummary() . PHP_EOL;
