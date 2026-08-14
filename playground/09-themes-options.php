<?php

/**
 * @file
 * Theme options: the built-in ones, and one a theme invents for itself.
 *
 * Display options are one string-keyed array on ->theme(): 'spacing' and
 * 'border' are built-ins, each carrying a case of its own enum, and 'accent'
 * is declared by the AccentTheme in themes/, whose allowed values are the
 * plain strings that theme enumerates in its option schema. Every value is
 * validated against that schema, so a typo throws at startup. The theme itself
 * is registered under a short alias with ThemeManager::register() - the third
 * selection route besides a built-in name and a class name (see
 * 09-themes-custom.php).
 *
 * Usage:
 *   php playground/09-themes-options.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Spacing;
use DrevOps\PhpTui\Theme\ThemeManager;
use DrevOps\PhpTui\Tui;
use Playground\Themes\AccentTheme;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/themes/AccentTheme.php';

// Register once, select by name everywhere after - the class is not named
// again at the use sites.
ThemeManager::register('accent', AccentTheme::class);

$form = Form::create('Theme options demo')
  ->panel('Order', function (PanelBuilder $p): void {
    $p->text('Name')->default('Weekly Box');
    $p->select('Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $p->select('Extras')->multiple()->options([
      'herbs' => 'Herbs',
      'nuts' => 'Nuts',
      'seeds' => 'Seeds',
    ]);
  });

try {
  $answers = (new Tui($form))
    ->theme('accent', [
      'spacing' => Spacing::Padded,
      'border' => Border::Rounded,
      'accent' => 'warm',
    ])
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
