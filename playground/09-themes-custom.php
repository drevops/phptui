<?php

/**
 * @file
 * Custom theme: a theme class named directly on the facade.
 *
 * The themes/OceanTheme.php class subclasses DefaultTheme and overrides
 * appearance atoms and render methods. Naming the class on the facade is the
 * lowest-friction way to use it - no registration; 09-themes-options.php shows
 * the registered-alias route. The form also carries a start banner, shown
 * before the panels with the version underneath.
 *
 * Usage:
 *   php playground/09-themes-custom.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;
use Playground\Themes\OceanTheme;

require __DIR__ . '/../vendor/autoload.php';
// The require makes the class loadable; a real consumer would autoload it.
require __DIR__ . '/themes/OceanTheme.php';

$banner = <<<'EOT'
  ~ ~ ~  O C E A N  ~ ~ ~
EOT;

$form = Form::create('Ocean theme demo')
  ->banner($banner)
  ->panel('stall', 'Seaside stall', function (PanelBuilder $p): void {
    $p->text('name', 'Stall name')->default('Harbour');
    $p->select('stock', 'Stock')->default('fruit')->options([
      'fruit' => 'Fruit',
      'veg' => 'Vegetables',
      'herbs' => 'Herbs',
    ]);
    $p->select('crates', 'Crates')->multiple()->options([
      'apples' => 'Apples',
      'pears' => 'Pears',
      'plums' => 'Plums',
    ]);
  });

try {
  // The banner comes from the form; the theme class and the border are set
  // on the facade. The version renders below the banner.
  $answers = (new Tui($form))
    ->theme(OceanTheme::class, ['border' => 'rounded'])
    ->run('', '1.0.0');
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
