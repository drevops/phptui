<?php

/**
 * @file
 * Agent help: generated instructions for driving the form unattended.
 *
 * The agentHelp() call describes the answers as a JSON Schema: every question
 * typed by its id, carrying its allowed values, its title, its default, the
 * environment variable that sets it, and - at the root - which of the answer
 * sources wins. Print it from your tool's --help so automation (or an agent)
 * can answer the form without reading its source.
 *
 * Usage:
 *   php playground/08-headless-agent-help.php
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

echo (new Tui($form))->agentHelp() . PHP_EOL;
