<?php

/**
 * @file
 * The 'dos' built-in theme: the retro MS-DOS installer look.
 *
 * The bright white/cyan/yellow CGA palette in a double-line window, painted
 * on its own blue surface regardless of the terminal background - the one
 * built-in theme that ignores the dark/light mode.
 *
 * Usage:
 *   php playground/09-themes-dos.php
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
  echo (new Tui($form))->theme('dos')->run()->toSummary() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
