<?php

/**
 * @file
 * Key bindings: caller commands and caller letters for a multiple select.
 *
 * A Command binds keys to a handler of your own. It runs from the panels -
 * never while a field is open - and the hint line advertises it beside the
 * built-in keys. Here "s" saves a draft of the order and "c" counts the
 * basket, and the multiple select checks every option on "a" and clears them
 * on "n". A command key that clashes with a built-in or another command
 * throws when the facade is configured.
 *
 * Usage:
 *   php playground/10-key-bindings-commands.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Binding;
use DrevOps\PhpTui\Input\Command;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Market order')
  ->panel('Order', function (PanelBuilder $p): void {
    $p->text('Order name')->default('Weekly');
    $p->select('Basket')->multiple()->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'pear' => 'Pear',
      'spinach' => 'Spinach',
    ]);
  });

$drafts = [];
$counts = [];

try {
  $answers = (new Tui($form))
    ->keys('default', [
      // Keep a copy of the answers as they stand.
      new Command('save', function (Answers $answers) use (&$drafts): void {
        $drafts[] = $answers->toSummary();
      }, 's'),
      // Count what is in the basket so far.
      new Command('count', function (Answers $answers) use (&$counts): void {
        $basket = $answers->value('basket');
        $counts[] = is_array($basket) ? count($basket) : 0;
      }, 'c'),
      // Letters stand in for the arrow keys in the multiple select.
      new Binding(Scope::field(FieldType::Select, multiple: TRUE), Action::SelectAll, 'a'),
      new Binding(Scope::field(FieldType::Select, multiple: TRUE), Action::SelectNone, 'n'),
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
echo sprintf('Drafts saved: %d', count($drafts)) . PHP_EOL;
echo sprintf('Basket counts: %s', $counts === [] ? 'none' : implode(', ', $counts)) . PHP_EOL;
