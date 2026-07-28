<?php

/**
 * @file
 * Template widget: fill the named slots of a fixed shape.
 *
 * The shape's fixed text is drawn as context and only the slots are typed
 * into. Tab or Down moves to the next slot, Up to the previous, arrows move
 * the caret inside the live slot, Enter accepts. The field collects the
 * assembled string; its parts come back from Answers::parts().
 *
 * Usage:
 *   php playground/02-widgets-template.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

$form = Form::create('Template widget')
  ->panel('main', 'Template', function (PanelBuilder $p): void {
    // ->pattern() declares the shape; ->placeholder() labels one slot and
    // gives it a validator of its own, checked apart from the others.
    $p->template('crate', 'Crate label')
      ->pattern('{{orchard}}-{{fruit}}-{{grade}}')
      ->default('valley-pear-a')
      ->placeholder('orchard', 'Orchard')
      ->placeholder('fruit', 'Fruit')
      ->placeholder('grade', 'Grade', static fn(string $value): ?string => preg_match('/^[a-c]$/', $value) === 1 ? NULL : 'use a single letter a-c');
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  $answers = (new Tui($form))->run();

  echo $answers->toJson() . PHP_EOL;
  // The whole label and the pieces it was built from, side by side.
  echo json_encode($answers->parts('crate')) . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
