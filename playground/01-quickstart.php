<?php

/**
 * @file
 * Quick start: one panel, five fields, collected through the Tui facade.
 *
 * The form is declared with the fluent Form builder and driven by the facade's
 * run(): a keyboard-driven TUI on a terminal, a non-interactive resolve from
 * defaults and environment variables otherwise. This is the form the
 * documentation's quick-start guide builds.
 *
 * Usage:
 *   php playground/01-quickstart.php
 *
 *   # Unattended: answers come from PHPTUI_<ID> environment variables and
 *   # defaults, no terminal needed ("name" is required, so it must be given).
 *   PHPTUI_NAME='Weekly Box' php playground/01-quickstart.php < /dev/null
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// A form is a titled set of panels; a panel is a titled set of fields. Each
// field method returns a builder, so options chain fluently off it.
$form = Form::create('Quick start')
  ->panel('order', 'New order', function (PanelBuilder $p): void {
    // A required single-line text field: the form cannot be submitted (or
    // resolved headlessly) without a value for it.
    $p->text('name', 'Order name')->required();

    // A single choice: arrows move, Enter accepts. The default names the
    // option value, not its label.
    $p->select('fruit', 'Fruit')->default('banana')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);

    // The same select with ->multiple(): Space toggles options and the field
    // collects a list of the checked values.
    $p->select('veg', 'Vegetables')->multiple()->default(['carrot'])->options([
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
      'spinach' => 'Spinach',
    ]);

    // An integer with bounds; the collected value is an int.
    $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);

    // A yes/no gate collecting a bool.
    $p->confirm('organic', 'Organic only?')->default(FALSE);
  });

try {
  // run() picks the mode: interactive when standard input is a terminal,
  // non-interactive otherwise. See playground/08-headless-* for the
  // non-interactive surface on its own.
  $answers = (new Tui($form))->run();
}
catch (InterruptException) {
  // Ctrl-C (or the Cancel button, whose CancelException subclasses this)
  // aborts the session; the partial answers are never returned.
  exit(130);
}
catch (CollectException $exception) {
  // A headless run without the required "name" lands here.
  fwrite(STDERR, $exception->getMessage() . PHP_EOL);
  exit(1);
}

// The self-describing summary: answers grouped by panel, values badged with
// where they came from. $answers->toJson() is the machine-readable twin.
echo $answers->toSummary() . PHP_EOL;
