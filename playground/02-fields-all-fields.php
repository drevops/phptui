<?php

/**
 * @file
 * Every field as a field on a single form - the whole gallery in one run.
 *
 * Each single-field script shows one field in isolation; this montage puts
 * one of every type on one panel to walk through in sequence. The file
 * pickers keep their standalone scripts - they browse a fixture tree that
 * would drown the montage.
 *
 * Usage:
 *   php playground/02-fields-all-fields.php
 */

declare(strict_types=1);

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Tui;

require __DIR__ . '/../vendor/autoload.php';

// Labels name the field type here, so the montage reads as a gallery; the
// single-field scripts show the same fields with real-world labels.
$form = Form::create('Fields')
  ->panel('fields', 'Fields', function (PanelBuilder $p): void {
    $p->note('note', 'Note')->body('A read-only card - the cursor skips it and it collects nothing.');
    $p->text('text', 'Text')->default('Pear');
    $p->template('template', 'Template')->pattern('{{orchard}}-{{fruit}}-{{grade}}')->default('valley-pear-a');
    $p->number('number', 'Number')->default(1200);
    $p->rating('rating', 'Rating')->default(4)->captions([1 => 'Poor', 3 => 'Fair', 5 => 'Excellent']);
    // The month grid wants the whole screen, so it opts out of inline
    // editing; every other field here edits in place on its row.
    $p->calendar('calendar', 'Calendar')->default('2026-07-15')->standalone();
    $p->textarea('textarea', 'Textarea')->default('Crisp and sweet' . chr(10) . 'Hint of citrus');
    $p->password('password', 'Password')->default('melon7');
    $p->select('select', 'Select')->default('apple')->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ]);
    $p->select('multiselect', 'MultiSelect')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->reorder('reorder', 'Reorder')->options([
      'apple' => 'Apple',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->suggest('suggest', 'Suggest')->options([
      'Apple' => 'Apple',
      'Apricot' => 'Apricot',
      'Banana' => 'Banana',
      'Cherry' => 'Cherry',
      'Mango' => 'Mango',
    ]);
    $p->search('search', 'Search')->default('carrot')->options([
      'carrot' => 'Carrot',
      'potato' => 'Potato',
      'onion' => 'Onion',
      'pepper' => 'Pepper',
    ]);
    $p->search('multisearch', 'MultiSearch')->multiple()->default(['apple'])->options([
      'apple' => 'Apple',
      'banana' => 'Banana',
      'carrot' => 'Carrot',
      'tomato' => 'Tomato',
    ]);
    $p->confirm('confirm', 'Confirm')->default(TRUE);
    $p->toggle('toggle', 'Toggle')->default('ripe')->options([
      'ripe' => 'Ripe',
      'unripe' => 'Unripe',
    ]);
    $p->pause('pause', 'Pause');
  });

try {
  // The rounded border frames the whole browser - the house look of the
  // panel demos; playground/03-panels-bordered.php shows it on its own.
  $answers = (new Tui($form))->theme('default', ['border' => Border::Rounded])->run();
}
catch (InterruptException) {
  // Leave quietly on Ctrl-C.
  exit(130);
}

// The summary groups the answers by panel with provenance badges.
echo $answers->toSummary() . PHP_EOL;
