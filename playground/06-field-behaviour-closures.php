<?php

/**
 * @file
 * Declared behaviour: required, default, validation and transform on a field.
 *
 * Four seams on any field, no classes needed: ->required() rejects an empty
 * value with a message derived from the label, or one declared through its
 * "message" argument; ->default() accepts a closure receiving the run Context
 * (directory, update flag, version) for values computed at run time;
 * ->validate() returns an error string to reject a value or NULL to accept it,
 * and blocks accepting until it passes; ->transform() rewrites the accepted
 * value before it is stored.
 *
 * Usage:
 *   php playground/06-field-behaviour-closures.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Handler\Context;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Field behaviour')
  ->panel('Stall', function (PanelBuilder $p): void {
    // A dynamic default: computed when the run starts, here from the target
    // directory's name ("sample-project" becomes "Sample Project"). Required,
    // so clearing it is rejected with a message derived from the label. Try
    // emptying it.
    $p->text('Stall name')->required()
      ->default(fn (Context $c): string => ucwords(str_replace(['-', '_'], ' ', basename($c->directory))));

    // A required field with its own message, shown instead of the derived one.
    // Nothing is picked to start with, so Submit refuses until one is.
    $p->select('Basket')->multiple()->required(message: 'Add at least one item to the basket.')
      ->option('apple', 'Apple')->option('carrot', 'Carrot')->option('tomato', 'Tomato');

    // Validation: NULL accepts, any string rejects with that message. Try
    // accepting a weight below 100.
    $p->number('Crate weight (g)')->default(1200)
      ->validate(fn (mixed $v): ?string => is_int($v) && $v >= 100 ? NULL : 'A crate weighs at least 100 g.');

    // A transform normalizes the accepted value - trimmed and lowercased
    // here - before it lands in the answers.
    $p->text('Variety')->default(' Golden Delicious ')
      ->transform(fn (mixed $v): mixed => is_string($v) ? strtolower(trim($v)) : $v);
  });

try {
  // The bundled sample-project/ directory stands in for the run's target, so
  // the dynamic default above resolves to the same name wherever the demo runs.
  $answers = (new Tui($form))->run(directory: __DIR__ . '/sample-project');
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
