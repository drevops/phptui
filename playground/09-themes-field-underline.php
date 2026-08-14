<?php

/**
 * @file
 * The underline field style: the input line drawn as an underline.
 *
 * The 'field' theme option styles the input line of the single-line editors
 * (text, number, password) while a value is typed: FieldStyle::Underline
 * underlines the entry area. FieldStyle::Flat (a plain caret) is the default
 * and FieldStyle::Boxed is the filled-bar style (see field-boxed.php). Press
 * Enter on a field to open its editor and see the style.
 *
 * Usage:
 *   php playground/09-themes-field-underline.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CollectException;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Theme\FieldStyle;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Field styles')
  ->panel('Order details', function (PanelBuilder $p): void {
    $p->description('Press Enter on a field to edit it and see the input style.');
    $p->text('Name')->default('Weekly Box');
    $p->number('Quantity')->default(6);
    $p->password('Order code')->default('melon7');
    // Empty by default: editing it shows the underline with no value on it.
    $p->text('Notes');
  });

try {
  $answers = (new Tui($form))->theme('default', ['field' => FieldStyle::Underline])->run();
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
