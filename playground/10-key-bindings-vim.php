<?php

/**
 * @file
 * Key bindings: the built-in 'vim' preset.
 *
 * ->keys('vim') adds h/j/k/l navigation alongside the arrows. Letters bind
 * only where they are not typed input, so text and filter fields keep their
 * arrow keys. The key-hint footer and the ? help overlay follow whatever is
 * bound, so they always tell the truth about the active keys. Bindings are a
 * TUI runtime setting, so they live on the facade, not the form.
 *
 * Usage:
 *   php playground/10-key-bindings-vim.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Key bindings demo')
  ->panel('order', 'Order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->default('Weekly');
    $p->select('fruit', 'Fruit')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->select('veg', 'Vegetables')->multiple()->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);
    $p->confirm('organic', 'Organic only?')->default(TRUE);
  });

try {
  // A preset is named like a theme: 'vim' here, a registered name, or a
  // KeyMap class name. Compare with custom.php, which retunes single
  // bindings on top of a preset.
  $answers = (new Tui($form))->keys('vim')->run();
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
