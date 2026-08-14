<?php

/**
 * @file
 * Folding the agent guide into a consumer tool's own help output.
 *
 * The library describes how to answer a form unattended; agentHelp() hands
 * that back as a JSON Schema of the answers. A consumer tool prints its own
 * help and includes that schema, so an AI agent (or any automation) reading
 * the help learns how to answer the form without reading the source. Where
 * the schema goes is the consumer's call - here it sits under an "AI agents"
 * heading; a real tool might gate it behind a flag of its own.
 *
 * Usage:
 *   php playground/08-headless-agent-cli.php
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
    $p->confirm('Organic only?')->default(FALSE);
  });

// The consumer tool's own help. agentHelp() supplies the machine-usable
// answer schema, so an agent reading the tool's help can drive the form -
// there is no separate contract to keep in sync.
echo 'produce-order - collect a produce order' . PHP_EOL;
echo PHP_EOL;
echo 'Run it to fill the order interactively.' . PHP_EOL;
echo PHP_EOL;
echo 'For AI agents:' . PHP_EOL;
echo PHP_EOL;
echo (new Tui($form))->agentHelp() . PHP_EOL;
