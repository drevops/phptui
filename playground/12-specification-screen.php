<?php

/**
 * @file
 * Draws a screen from the levels the specification describes.
 *
 * One form declaration reaches both paths: the builder writes a tree of blocks,
 * the assembler puts the standard furniture around it, and each block reaches
 * the theme for its own elements. Keys travel inward to the innermost thing
 * that binds them, and the legend rewrites itself from whatever that turns out
 * to be. The same tree collects with no screen at all, which is the point of
 * the split.
 *
 * Unlike the rest of the playground it opens no session of its own: it prints
 * the frames a screen draws, so it reads the same on any terminal and on none.
 *
 * Usage:
 *   php playground/12-specification-screen.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Block\Breadcrumb;
use DrevOps\PhpTui\Block\Legend;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Assembler;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Collector;
use DrevOps\PhpTui\Screen\KeyRouter;
use DrevOps\PhpTui\Screen\ScreenRenderer;
use DrevOps\PhpTui\Testing\ScreenTester;
use DrevOps\PhpTui\Theme\DefaultTheme;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Orchard')
  ->panel('Delivery', function (PanelBuilder $p): void {
    $p->text('Courier')->default('Valley Runs')->help('Every crate is weighed and labelled at the packing bench.');
    $p->markup('weighing', 'Weighed at the packing bench.');
    $p->number('Basket weight')->default(1200)->min(200)->max(9000);
    $p->select('Basket contents')->multiple()->option('apple', 'Apple')->option('carrot', 'Carrot')->default(['apple']);
  });

// Wide enough that the editor's legend is read rather than clipped: a region
// hands back the rows it was given, so anything past them is cut.
$theme = new DefaultTheme(72);
$panel = $form->root();
$screen = (new Assembler())->assemble($panel);
$breadcrumb = $screen->in('header')->blocks()[0];
$legend = $screen->in('footer')->blocks()[0];
$router = new KeyRouter($panel);

$frame = static function (string $said) use ($router, $breadcrumb, $legend, $screen, $theme): void {
  // The legend is written from the innermost binder rather than beside it, so
  // it says something different the moment a field takes the keys.
  if ($legend instanceof Legend) {
    $router->refresh($legend);
  }

  if ($breadcrumb instanceof Breadcrumb) {
    $breadcrumb->trail(...$router->trail());
  }

  print $said . "\n\n";
  print (new ScreenRenderer($theme))->render($screen, 10, 72) . "\n\n";
};

print "On screen\n\n";
$frame('The cursor rests on the first block that takes it.');

print "Driven by keys, one at a time\n\n";

$router->handle(Key::named(KeyName::Enter));
$frame('Enter goes into the panel, and the trail grows a segment.');

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
  (new Collector())->collect($panel, ['basket_weight' => 10]);
}
catch (CollectException $exception) {
  print '  ' . $exception->getMessage() . "\n";
}

print "\nA warning beside the breadcrumb, without nesting a layout\n\n";

// Blocks run across the header rather than down it, so two sit side by side.
$screen->in('header')->flow(Axis::Columns)->add(new Markup('preview', '(read-only preview)'));
print (new ScreenRenderer($theme))->render($screen, 10, 72) . "\n";

print "\nDriven as a session, from the first frame to the submit\n\n";

// A second declaration, so the session opens on a form nobody has typed into.
$driven = Form::create('Orchard')
  ->panel('Delivery', function (PanelBuilder $p): void {
    $p->text('Courier')->default('Valley Runs');
    $p->number('Basket weight')->default(1200)->min(200)->max(9000);
  });

// The session reads keys from a terminal and writes frames back to it, so it is
// driven here through the scripted terminal the test harness wraps.
$session = (new ScreenTester($driven->root()))->rows(9)->cols(72);

try {
  $collected = $session->run(
    Key::named(KeyName::Enter),
    Key::named(KeyName::Enter),
    ' Coast',
    Key::named(KeyName::Enter),
    Key::named(KeyName::Escape),
    Key::named(KeyName::Down),
    Key::named(KeyName::Enter),
  );
}
catch (InterruptException $exception) {
  // Ctrl-C aborts from anywhere and the cancel button raises the same
  // exception's subclass, so every way of ending without a submit leaves here.
  print $exception->getMessage() . "\n";

  exit(130);
}

foreach ([1, 3, 5] as $index) {
  print $session->frame($index) . "\n\n";
}

print "Collected by the session\n\n";

foreach (['courier', 'basket_weight'] as $id) {
  printf("  %-8s %s\n", $id, var_export($collected->value($id), TRUE));
}
