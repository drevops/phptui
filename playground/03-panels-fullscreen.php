<?php

/**
 * @file
 * Fullscreen: the panel browser stretched to the whole terminal screen.
 *
 * Fullscreen is a facade switch (->fullscreen()); where the content sits
 * inside the stretched frame is a pair of theme options - 'halign' (an HAlign
 * case) and 'valign' (a VAlign case) - set alongside the border, here picked
 * by the --halign and --valign flags so every alignment is one run away. A
 * 'max_width' cap (--max-width) floats the frame like a dialog at the chosen
 * anchor, and below 'min_width' / 'min_height' (the width is measured from
 * the form's own content unless set) the TUI shows a resize notice instead of
 * a broken layout. The form arranges its panels with ->layout(1, 2), so the
 * stretched screen shows the grid the layout example walks through.
 *
 * Usage:
 *   php playground/03-panels-fullscreen.php                    # centered
 *   php playground/03-panels-fullscreen.php --halign=left --valign=top
 *   php playground/03-panels-fullscreen.php --halign=right --valign=bottom
 *   php playground/03-panels-fullscreen.php --max-width=60
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\VAlign;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['halign::', 'valign::', 'max-width::']);
$max_width = array_key_exists('max-width', $options) && is_numeric($options['max-width']) ? (int) $options['max-width'] : 0;
$across = is_string($options['halign'] ?? NULL) ? $options['halign'] : '';
$down = is_string($options['valign'] ?? NULL) ? $options['valign'] : '';

// An alignment is one of a fixed set, so a flag is read straight into the case
// that names it, and one nobody offers is refused by name here rather than
// reaching the theme as a string it would have to vet.
$halign = $across === '' ? HAlign::Center : HAlign::tryFrom($across) ?? throw new InvalidArgumentException('Unknown --halign: use left, center or right.');
$valign = $down === '' ? VAlign::Middle : VAlign::tryFrom($down) ?? throw new InvalidArgumentException('Unknown --valign: use top, middle or bottom.');

$form = Form::create('Market stall')
  ->layout(1, 2)
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('summary', 'Summary', function (PanelBuilder $p): void {
    $p->description('The order at a glance.');
    $p->text('name', 'Order name')->default('Weekly Box')->required();
  })
  ->panel('produce', 'Produce', function (PanelBuilder $p): void {
    $p->layout(2);
    $p->panel('fruit', 'Fruit', function (PanelBuilder $sp): void {
      $sp->select('fruit', 'Fruit')->default('apple')->options([
        'apple' => 'Apple',
        'banana' => 'Banana',
        'cherry' => 'Cherry',
      ]);
    });
    $p->panel('veg', 'Vegetables', function (PanelBuilder $sp): void {
      $sp->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
        'carrot' => 'Carrot',
        'tomato' => 'Tomato',
        'spinach' => 'Spinach',
      ]);
    });
  })
  ->panel('delivery', 'Delivery', function (PanelBuilder $p): void {
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);
  });

try {
  // A bad option value throws at startup, not mid-session.
  $answers = (new Tui($form))
    ->theme('default', [
      'border' => Border::Rounded,
      'halign' => $halign,
      'valign' => $valign,
      'max_width' => $max_width,
    ])
    ->fullscreen()
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

// The summary groups the answers by panel with provenance badges.
echo $answers->toSummary() . PHP_EOL;
