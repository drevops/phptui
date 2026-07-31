<?php

/**
 * @file
 * The boxed field style: a filled input bar behind the value.
 *
 * The 'field' theme option styles the input line of the single-line editors
 * (text, number, password) while a value is typed: FieldStyle::Boxed fills a
 * fixed-width background block - visible even when the field is empty, the
 * MS-DOS installer look. FieldStyle::Flat (a plain caret) is the default and
 * FieldStyle::Underline is the third style (see field-underline.php). Press
 * Enter on a field to open its editor and see the bar.
 *
 * Usage:
 *   php playground/09-themes-field-boxed.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\FieldStyle;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Field styles')
  ->panel('order', 'Order details', function (PanelBuilder $p): void {
    $p->description('Press Enter on a field to edit it and see the input style.');
    $p->text('name', 'Name')->default('Weekly Box');
    $p->number('quantity', 'Quantity')->default(6);
    $p->password('code', 'Order code')->default('melon7');
    // Empty by default: editing it shows the boxed style as an empty bar.
    $p->text('notes', 'Notes');
  });

try {
  $answers = (new Tui($form))->theme('default', ['field' => FieldStyle::Boxed])->run();
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
