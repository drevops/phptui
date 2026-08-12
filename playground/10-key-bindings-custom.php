<?php

/**
 * @file
 * Key bindings: retuning single bindings on top of a preset.
 *
 * Each override is a Binding naming a scope (the base map, navigation, or one
 * field type), an action, and the keys that trigger it. Overrides apply on
 * top of the named preset; a conflicting or un-typeable binding throws when
 * the facade is configured, not mid-session, so a bad map cannot ship.
 *
 * Usage:
 *   php playground/10-key-bindings-custom.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Binding;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Key bindings demo')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly');
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // Start from the default preset and retune two bindings; the footer hints
  // pick up both changes.
  $answers = (new Tui($form))
    ->keys('default', [
      // Quit with x as well as q.
      new Binding(Scope::navigation(), Action::Quit, 'x'),
      // In the single-choice list, Tab accepts too (Enter still does). A
      // scope can target one field type without touching the others.
      new Binding(Scope::field(FieldType::Select), Action::Accept, KeyName::Tab, KeyName::Enter),
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
