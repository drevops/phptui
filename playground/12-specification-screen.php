<?php

/**
 * @file
 * Draws a screen from the levels the specification describes.
 *
 * A layout declares regions and names no block; the assembler puts the
 * standard furniture in them; each block reaches the theme for its own
 * elements. Keys travel inward to the innermost thing that binds them, and
 * the legend rewrites itself from whatever that turns out to be. The same
 * panel collects with no screen at all, which is the point of the split.
 *
 * Usage:
 * @code
 * php playground/12-specification-screen.php
 * @endcode
 */

declare(strict_types=1);

use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Screen\Assembler;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\KeyRouter;
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
  ->field('basket', 'Basket contents', FieldType::Select)
  ->multiple()
  ->entry('apple', 'Apple')
  ->entry('carrot', 'Carrot')
  ->default(['apple'])
  ->done()
  ->build();

// Wide enough that the editor's legend is read rather than clipped: a region
// hands back the rows it was given, so anything past them is cut.
$theme = new DefaultTheme(72);
$screen = (new Assembler())->assemble($panel);
$legend = $screen->in('footer')->blocks()[0];
$router = new KeyRouter($panel);

$frame = static function (string $said) use ($router, $legend, $screen, $theme): void {
  // The legend is written from the innermost binder rather than beside it, so
  // it says something different the moment a field takes the keys.
  if ($legend instanceof Legend) {
    $router->refresh($legend);
  }

  print $said . "\n\n";
  print (new ScreenRenderer($theme))->render($screen, 10, 72) . "\n\n";
};

print "On screen\n\n";
$frame('The cursor rests on the first block that takes it.');

print "Driven by keys, one at a time\n\n";

$router->handle(Key::named(KeyName::Down));
$router->handle(Key::named(KeyName::Down));
$frame('Down twice, skipping the markup that never takes the cursor.');

$router->handle(Key::named(KeyName::Enter));
$frame('Enter opens the field, and the legend belongs to the editor now.');

$router->handle(Key::named(KeyName::Down));
$router->handle(Key::named(KeyName::Space));
$frame('Space toggles an entry, because the editor is what binds it.');

$router->handle(Key::named(KeyName::Escape));
$frame('Escape closes it, discarding both the toggle and those keys.');

print "Collected headlessly, with no screen at all\n\n";

$readable = static fn(mixed $part): string => is_scalar($part) ? (string) $part : '';

foreach ((new Collector())->collect($panel) as $id => $value) {
  printf("  %-8s %s\n", $id, is_array($value) ? implode(', ', array_map($readable, $value)) : var_export($value, TRUE));
}

print "\nA value the field refuses\n\n";

try {
  (new Collector())->collect($panel, ['weight' => 10]);
}
catch (InvalidArgumentException $exception) {
  print '  ' . $exception->getMessage() . "\n";
}

print "\nA warning beside the breadcrumb, without nesting a layout\n\n";

// Blocks run across the header rather than down it, so two sit side by side.
$screen->in('header')->flow(Axis::Columns)->add(new Markup('note', '(read-only preview)'));
print (new ScreenRenderer($theme))->render($screen, 10, 72) . "\n";
