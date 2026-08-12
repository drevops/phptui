<?php

/**
 * @file
 * The 'mono' built-in theme: hue-free, for maximum compatibility.
 *
 * Bold weight, grey levels and reverse video do all the work - no colour
 * assumptions about the terminal at all. A built-in theme is selected by
 * name on the facade.
 *
 * Usage:
 *   php playground/09-themes-mono.php
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
  ->panel('preview', 'Preview', function (PanelBuilder $p): void {
    $p->text('name', 'Box name')->default('Weekly Box')->description('Shown in the header.');
    $p->select('grade', 'Grade')->default('premium')->description('Quality grade.')->options([
      'basic' => 'Basic',
      'premium' => 'Premium',
      'organic' => 'Organic',
    ]);
    $p->select('extras', 'Extras')->multiple()->default(['herbs', 'nuts'])->description('Added extras.')->options([
      'herbs' => 'Herbs',
      'nuts' => 'Nuts',
      'seeds' => 'Seeds',
      'flowers' => 'Flowers',
    ]);
    $p->confirm('gift', 'Gift wrap')->default(TRUE)->description('Wrap the box as a gift.');
  });

try {
  echo (new Tui($form))->theme('mono')->run()->toSummary() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
