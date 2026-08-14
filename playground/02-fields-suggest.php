<?php

/**
 * @file
 * Suggest field: free text with autocomplete over a fixed option set.
 *
 * As you type, suggestions are fuzzy-matched and ranked by relevance; arrows
 * highlight one and Enter takes it. Unlike select, any typed text is a valid
 * answer - the options only assist.
 *
 * ->ghost() adds an inline preview of the leading match after the caret,
 * accepted with Tab or the right arrow; the ranked list stays available.
 *
 * Usage:
 *   php playground/02-fields-suggest.php
 */

declare(strict_types=1);

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// One field on one panel: the smallest form that exercises the field.
$form = Form::create('Suggest field')
  ->panel('Suggest', function (PanelBuilder $p): void {
    $p->suggest('Fruit')->options([
      'Apple' => 'Apple',
      'Apricot' => 'Apricot',
      'Banana' => 'Banana',
      'Cherry' => 'Cherry',
      'Mango' => 'Mango',
    ])->ghost();
  });

try {
  // Interactive on a terminal; resolved from the default when piped.
  echo (new Tui($form))->run()->toJson() . PHP_EOL;
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}
