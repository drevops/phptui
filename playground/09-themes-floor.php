<?php

/**
 * @file
 * Floor theme: a theme class that declares no capability at all.
 *
 * The themes/PlainTheme.php class extends AbstractTheme - the floor - rather
 * than DefaultTheme, so it declares neither colour nor Unicode nor any of the
 * other four facilities, and styles two elements with plain characters.
 * Naming the class on the facade is all it takes: the theme manager asks a
 * theme for the floor and nothing above it, so anything implementing the
 * theme interface is a theme it builds and a session runs.
 *
 * What the theme does not declare, the driver does without: no frame is drawn
 * around the form, no blank row shows between blocks, nothing recedes behind a
 * dialog, and the colour and Unicode switches below never reach it - so the
 * frame reads the same on a terminal that offers everything and one that
 * offers nothing.
 *
 * Usage:
 *   php playground/09-themes-floor.php
 *
 *   # The switches change nothing: a theme is granted only what it declares.
 *   NO_COLOR=1 LC_ALL=C php playground/09-themes-floor.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;
use Playground\Themes\PlainTheme;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the class loadable; a real consumer would autoload it.
require __DIR__ . '/themes/PlainTheme.php';

$form = Form::create('Floor theme demo')
  ->panel('Market stall', function (PanelBuilder $p): void {
    $p->markup('plain', 'Drawn with no colour, no glyphs and no frame.');
    $p->text('Stall name')->default('Harbour');
    $p->select('Stock')->default('fruit')->options([
      'fruit' => 'Fruit',
      'veg' => 'Vegetables',
      'herbs' => 'Herbs',
    ]);
    $p->confirm('Organic only?')->default(TRUE);
  });

try {
  // The class is named directly - a theme on the floor is selected exactly as
  // any other is, and needs no registration.
  $answers = (new Tui($form))
    ->theme(PlainTheme::class)
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

echo $answers->toSummary() . PHP_EOL;
