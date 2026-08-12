<?php

/**
 * @file
 * Custom layouts: a consumer's own arrangement, registered and picked by name.
 *
 * A layout is a class extending AbstractLayout that declares an axis and its
 * regions; the arithmetic is inherited. Register one under a short name with
 * LayoutManager::register() and it is available everywhere a shipped name is -
 * on the screen through the facade's ->layout(), and on a panel through the
 * builder's ->layout(). One registration serves both, which is what makes a
 * layout reusable where a region is not.
 *
 * layouts/StallLayout.php runs its regions across, sharing the width between a
 * produce column that scrolls and a delivery column that does not, and
 * arranges the panel; layouts/MarketLayout.php runs its regions down, giving
 * two of them a fixed number of rows, and arranges the screen. A name nothing
 * answers to throws where ->layout() is written, not mid-session.
 *
 * Usage:
 *   php playground/20-layouts-custom.php
 *
 *   # Unattended: the arrangement is drawing, so the answers resolve the same.
 *   php playground/20-layouts-custom.php < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Screen\Layout\LayoutManager;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Tui;
use Playground\Layouts\MarketLayout;
use Playground\Layouts\StallLayout;

require __DIR__ . '/../vendor/autoload.php';
// The requires make the classes loadable; a real consumer would autoload them.
require __DIR__ . '/layouts/StallLayout.php';
require __DIR__ . '/layouts/MarketLayout.php';

// Registered once, named everywhere after. Passing the class itself to
// ->layout() works too, and needs no registration at all.
LayoutManager::register('stall', StallLayout::class);
LayoutManager::register('market', MarketLayout::class);

$form = Form::create('Market stall')
  ->buttons(TRUE, 'Place order', 'Cancel')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    // Declared before anything is placed, so every block below knows the
    // regions it may go in.
    $p->layout('stall');

    // A block says which region it belongs to; the ones after it keep that
    // region until another is named.
    $p->in('produce');
    $p->text('item', 'Item')->default('Pear');
    $p->number('crates', 'Crates')->default(6)->min(1)->max(99);
    $p->select('basket', 'Basket')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);

    $p->in('delivery');
    $p->confirm('gift', 'Gift wrap?')->default(FALSE);
    $p->suggest('day', 'Delivery day')->default('Friday')->options([
      'Monday' => 'Monday',
      'Wednesday' => 'Wednesday',
      'Friday' => 'Friday',
    ]);
  });

try {
  // The screen's arrangement is a separate choice from the panel's: this one
  // keeps a header, a content region and a footer, so the trail, the form and
  // the key hints all have somewhere to go.
  $answers = (new Tui($form))
    ->layout('market')
    ->theme('default', ['border' => Border::Rounded])
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

// Regions arrange drawing and nothing else, so the answers read exactly as
// they would under any other layout.
echo $answers->toSummary() . PHP_EOL;
