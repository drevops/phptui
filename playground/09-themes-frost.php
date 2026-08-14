<?php

/**
 * @file
 * The 'frost' theme: frost-blue accents, sage values, sand highlights.
 *
 * A built-in theme is selected by name on the facade. Dark or light is not
 * part of the theme - it is a separate 'mode' display option, auto-detected
 * from the terminal background here; see playground/11-display-modes-*.
 *
 * Usage:
 *   php playground/09-themes-frost.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// The same preview form as the other theme scripts, so only the palette
// differs between them.
$form = Form::create('Theme preview')
  ->panel('Preview', function (PanelBuilder $p): void {
    $p->text('Box name')->default('Weekly Box')->description('Shown in the header.');
    $p->select('Grade')->default('premium')->description('Quality grade.')->options([
      'basic' => 'Basic',
      'premium' => 'Premium',
      'organic' => 'Organic',
    ]);
    $p->select('Extras')->multiple()->default(['herbs', 'nuts'])->description('Added extras.')->options([
      'herbs' => 'Herbs',
      'nuts' => 'Nuts',
      'seeds' => 'Seeds',
      'flowers' => 'Flowers',
    ]);
    $p->confirm('Gift wrap')->default(TRUE)->description('Wrap the box as a gift.');
  });

try {
  echo (new Tui($form))->theme('frost')->run()->toSummary() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
