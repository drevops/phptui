<?php

/**
 * @file
 * The produce box: every major feature composed into one real form.
 *
 * The capstone example - each numbered playground directory shows one
 * feature in isolation; this walkthrough combines them the way a real
 * consumer would: two panels of mixed fields, a derived-value chain,
 * conditional fields, declared behaviour closures, and the bordered panel
 * TUI, collected through the one facade call.
 *
 * Usage:
 *   php playground/14-produce-box.php
 *
 *   # Unattended, with per-field environment overrides:
 *   PHPTUI_BOX_NAME='Summer Box' php playground/14-produce-box.php < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\Handler\Context;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Produce box')
  ->panel('Basics', function (PanelBuilder $p): void {
    $p->description('Naming and identity.');

    // Declared behaviour (playground/06-field-behaviour-*): a dynamic default
    // computed from the run context, validation, and a transform - all
    // closures on the field.
    $p->text('Box name')->description('A human-readable name, e.g. "Summer Box".')->required()
      ->default(fn (Context $c): string => ucwords(str_replace(['-', '_'], ' ', basename($c->directory))))
      ->validate(fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? NULL : 'The box name is required.')
      ->transform(fn (mixed $v): mixed => is_string($v) ? trim($v) : $v);

    // A derived-value chain (playground/05-form-logic-*): the slug follows the
    // name, and the code follows the grower and the slug.
    $p->text('Slug')->description('Derived from the box name.')->derive(new Derive('{{box_name}}', 'machine'));
    $p->text('Grower')->default('sunny');
    $p->text('Box code')->description('Derived from grower and slug.')->derive(new Derive('{{grower}}/{{slug}}', 'lower'));
    $p->text('Label')->derive(new Derive('{{box_name}}', 'pascal'));
  })
  ->panel('Contents & options', function (PanelBuilder $p): void {
    $p->description('What the box ships with.');

    $p->select('Box size')->default('medium')->options([
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $p->select('Contents')->multiple()->description('Space to toggle, type to filter.')->options([
      'fruit' => 'Fruit',
      'veg' => 'Vegetables',
      'herbs' => 'Herbs',
      'salad' => 'Salad',
    ]);

    // Conditional fields (playground/05-form-logic-*): shown only while herbs
    // are among the contents; the weekly gate composes two conditions.
    $p->text('Herb bundle')->default('mixed')->when(new Condition('contents', contains: 'herbs'));
    $p->confirm('Weekly delivery?')->default(TRUE)->when(Condition::all(new Condition('contents', contains: 'herbs'), new Condition('box_size', eq: 'large')));

    // An autocomplete with free-text fallback.
    $p->suggest('Delivery day')->default('Friday')->options([
      'Monday' => 'Monday',
      'Wednesday' => 'Wednesday',
      'Friday' => 'Friday',
      'Saturday' => 'Saturday',
    ]);
    $p->confirm('Gift wrap?')->default(FALSE);
  });

try {
  // The bordered browser (playground/03-panels-*); the version renders in the
  // context and run() picks interactive or unattended
  // (playground/08-headless-*).
  $answers = (new Tui($form))
    ->theme('default', ['border' => Border::Rounded])
    ->run('', '1.0.0');
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
catch (CollectException $exception) {
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// Self-describing answers (playground/08-headless-*): grouped by panel, badged
// with provenance.
echo $answers->toSummary() . PHP_EOL;
