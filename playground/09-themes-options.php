<?php

/**
 * @file
 * Theme options: built-in and theme-invented, all as plain strings.
 *
 * Display options are one string-keyed array on ->theme(): 'spacing' and
 * 'border' are built-ins, 'accent' is declared by the AccentTheme in themes/.
 * Every value is validated against the theme's option schema, so a typo throws
 * at startup. The theme itself is registered under a short alias with
 * ThemeManager::register() - the third selection route besides a built-in name
 * and a class name (see 09-themes-custom.php).
 *
 * Usage:
 *   php playground/09-themes-options.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Tui;
use Playground\Themes\AccentTheme;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/themes/AccentTheme.php';

// Register once, select by name everywhere after - the class is not named
// again at the use sites.
ThemeManager::register('accent', AccentTheme::class);

$form = Form::create('Theme options demo')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->text('name', 'Name')->default('Weekly Box');
    $p->select('size', 'Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $p->select('extras', 'Extras')->multiple()->options([
      'herbs' => 'Herbs',
      'nuts' => 'Nuts',
      'seeds' => 'Seeds',
    ]);
  });

try {
  $answers = (new Tui($form))
    ->theme('accent', [
      'spacing' => 'padded',
      'border' => 'rounded',
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
