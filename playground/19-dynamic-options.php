<?php

/**
 * @file
 * Options resolved from the answers collected so far.
 *
 * A callback handed to ->options() that asks for the run context is called
 * again whenever the answers change, so one field's choices can narrow by
 * another's answer. It runs as part of the form settling, before anything is
 * drawn or validated, so the narrowed list is what the panel offers, what a
 * headless payload is checked against and what the schema advertises - and a
 * choice the narrowed list no longer holds is dropped from the answers, unless
 * it was supplied headlessly, which collection reports instead.
 *
 * Usage:
 *   php playground/19-dynamic-options.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$catalog = [
  'fruit' => ['apple' => 'Apple', 'banana' => 'Banana', 'cherry' => 'Cherry'],
  'vegetable' => ['carrot' => 'Carrot', 'potato' => 'Potato', 'tomato' => 'Tomato'],
];

$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p) use ($catalog): void {
    $p->text('name', 'Order name')->required();

    $p->select('category', 'Category')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable'])->default('fruit');

    // An answer is whatever was supplied until it is validated, so the category
    // is read defensively before it indexes anything.
    $stock = static function (Context $context) use ($catalog): array {
      $category = $context->answers['category'] ?? '';

      return is_string($category) ? ($catalog[$category] ?? []) : [];
    };

    // Called again whenever the answers change: pick another category and the
    // item list follows, dropping an item the new category does not stock.
    $p->select('item', 'Item')->options($stock);

    // The same narrowing over several picks - the basket keeps only what the
    // chosen category still offers.
    $p->select('basket', 'Basket')->multiple()->options($stock);
  });

try {
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  exit(130);
}
