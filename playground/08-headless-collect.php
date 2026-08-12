<?php

/**
 * @file
 * Headless collection: the same form answered without a terminal.
 *
 * The collect() call resolves every field from, in order of precedence:
 * the prompts JSON, per-field environment variables (PHPTUI_<ID> by default),
 * discovered values, derived values, then the declared default. Nothing
 * prompts, so the same form drives CI and automation unchanged.
 *
 * Usage:
 *   php playground/08-headless-collect.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce order')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    $p->text('name', 'Order name')->required();
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

// A per-field environment variable: the uppercased field id under the PHPTUI_
// prefix. Exported by the calling shell in real use; set here so the demo is
// self-contained. Form::envPrefix() or the facade can change the prefix.
putenv('PHPTUI_ORGANIC=1');

// Answers arrive as a JSON object keyed by field id - inline here, but
// collect() also accepts a path to a JSON file. The prompts win over the
// environment, so "fruit" is cherry even if PHPTUI_FRUIT were set.
$prompts = '{"name": "Weekly Box", "fruit": "cherry"}';

try {
  $answers = (new Tui($form))->collect($prompts);
}
catch (CollectException $exception) {
  // A missing required answer or a value failing validation lands here.
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// toJson() is the machine-readable result: every field id to its typed
// value, ready to pipe onward.
echo $answers->toJson() . PHP_EOL;
echo PHP_EOL;

// toSummary() is the human-readable twin: grouped by panel, each answer
// badged with its provenance - "edited" for the prompt and env inputs here,
// no badge for untouched defaults.
echo $answers->toSummary() . PHP_EOL;
