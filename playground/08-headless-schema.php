<?php

/**
 * @file
 * Self-describing forms: the JSON schema and answer validation.
 *
 * The schema() call renders the declared questions as a JSON schema - ids,
 * types, options, bounds, requiredness - for editors, pipelines or any tool
 * that wants to know the questions without running the form. validate()
 * checks an answer set against the same rules and returns the violations.
 *
 * Usage:
 *   php playground/08-headless-schema.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('New order', function (PanelBuilder $p): void {
    $p->text('Order name')->required();
    $p->select('Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('Quantity')->min(1)->max(99)->default(6);
  });

$tui = new Tui($form);

// The machine-readable description of every question.
echo json_encode($tui->schema(), JSON_PRETTY_PRINT) . PHP_EOL;
echo PHP_EOL;

// Validation without collection: each violation is one message. This set
// trips two rules - "grape" is not an option and 500 is over the maximum -
// and an empty list means the answers are valid.
$errors = $tui->validate(['order_name' => 'Weekly Box', 'fruit' => 'grape', 'quantity' => 500]);

foreach ($errors as $error) {
  echo '- ' . $error . PHP_EOL;
}
