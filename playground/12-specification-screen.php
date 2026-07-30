<?php

/**
 * @file
 * Draws a screen from the levels the specification describes.
 *
 * A layout declares regions and names no block; the assembler puts the standard
 * furniture in them; each block reaches the theme for its own elements. The same
 * panel collects with no screen at all, which is the point of the split.
 *
 * Usage:
 * @code
 * php playground/12-specification-screen.php
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\PanelBuilder;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Theme\DefaultTheme;

require __DIR__ . '/../vendor/autoload.php';

$panel = (new PanelBuilder('delivery', 'Delivery'))
  ->field('courier', 'Courier')
    ->default('Valley Runs')
    ->help('Every crate is weighed and labelled at the packing bench.')
  ->done()
  ->markup('weighing', 'Weighed at the packing bench.')
  ->field('weight', 'Basket weight')
    ->default(1200)
    ->constrain('a weight between 200 and 9000')
    ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 && $value <= 9000 ? NULL : 'Enter a weight between 200 and 9000.')
  ->done()
  ->field('basket', 'Basket contents')
    ->entry('apple', 'Apple')
    ->entry('carrot', 'Carrot')
    ->default('apple')
  ->done()
  ->build();

$theme = new DefaultTheme(56);

print "On screen\n\n";
print (new ScreenRenderer($theme))->render((new Assembler())->assemble($panel), 10, 56) . "\n\n";

print "The same panel, with a field open\n\n";
$panel->in('content')->blocks()[3]->open();
print (new ScreenRenderer($theme))->render((new Assembler())->assemble($panel), 10, 56) . "\n\n";

print "Collected headlessly, with no screen at all\n\n";
foreach ((new Collector())->collect($panel) as $id => $value) {
  printf("  %-8s %s\n", $id, is_array($value) ? implode(', ', $value) : var_export($value, TRUE));
}

print "\nA value the field refuses\n\n";

try {
  (new Collector())->collect($panel, ['weight' => 10]);
}
catch (InvalidArgumentException $exception) {
  print '  ' . $exception->getMessage() . "\n";
}

print "\nA warning beside the breadcrumb, without nesting a layout\n\n";
$screen = (new Assembler())->assemble($panel);

// Blocks run across the header rather than down it, so two sit side by side.
$screen->in('header')->flow(Axis::Columns)->add(new Markup('note', '(read-only preview)'));
print (new ScreenRenderer($theme))->render($screen, 10, 56) . "\n";
