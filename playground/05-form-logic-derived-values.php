<?php

/**
 * @file
 * Derived values: fields computed from other answers via templates.
 *
 * A Derive rule is a "{{field}}" template plus an optional named transform
 * ('machine', 'kebab', 'constant', 'lower', ...). Derived fields re-settle
 * whenever a referenced answer changes - edit the name interactively and
 * watch the chain follow - and derivation runs to a fixpoint, so a derived
 * field can feed another derived field. An explicit answer to a derived field
 * wins over its rule.
 *
 * Usage:
 *   php playground/05-form-logic-derived-values.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Derived values')
  ->panel('naming', 'Naming', function (PanelBuilder $p): void {
    // The one field a person actually types.
    $p->text('name', 'Produce name')->default('Red Apple')->required();

    // "Red Apple" -> "red_apple": the 'machine' transform of the name.
    $p->text('slug', 'Slug')->description('Derived from the name.')->derive(new Derive('{{name}}', 'machine'));

    // A chain: the derived slug feeds this rule. "red_apple" -> "RED_APPLE".
    $p->text('code', 'Code')->description('Derived from the slug.')->derive(new Derive('{{slug}}', 'constant'));

    // A template mixing two answers, then lowercased.
    $p->text('grower', 'Grower')->default('Sunny');
    $p->text('lot', 'Lot')->description('Derived from grower and slug.')->derive(new Derive('{{grower}}/{{slug}}', 'lower'));
  });

try {
  $answers = (new Tui($form))->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Derived answers carry the "derived" provenance badge in the summary.
echo $answers->toSummary() . PHP_EOL;
