<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Form;

use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Primitive\ProgressReporter;

/**
 * Test fixture: a form exercising one field of every field type.
 *
 * Every FieldType is represented exactly once so the panel TUI can be driven
 * through each field in a single run; a guard test asserts the coverage stays
 * complete as field types are added.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Form
 */
final class AllFieldsForm {

  /**
   * Build the fixture form.
   *
   * @param string $picker_start
   *   The directory the file-picker fields open at (a controlled directory so
   *   the browser lands on a known entry).
   *
   * @return \DrevOps\PhpTui\Builder\Form
   *   The form.
   */
  public static function create(string $picker_start = ''): Form {
    return Form::create('All fields')
      ->panel('Fields', 'fields', function (PanelBuilder $p) use ($picker_start): void {
        $p->note('Note', 'note')->body('A read-only note field.');
        $p->text('Text', 'text')->default('txt');
        $p->template('Template', 'template')->pattern('{{head}}-{{tail}}')->default('a-b');
        $p->number('Number', 'number')->default(7);
        $p->rating('Rating', 'rating')->captions([1 => 'Poor', 5 => 'Excellent'])->default(4);
        $p->calendar('Calendar', 'date')->default('2026-07-15');
        $p->textarea('Textarea', 'textarea')->default('note');
        $p->password('Password', 'password')->default('pw');
        $p->select('Select', 'select')->options(['a' => 'Alpha', 'b' => 'Beta'])->default('b');
        $p->select('MultiSelect', 'multiselect')->multiple()->options(['a' => 'Alpha', 'b' => 'Beta'])->default(['a']);
        $p->suggest('Suggest', 'suggest')->options(['utc' => 'UTC', 'gmt' => 'GMT'])->default('utc');
        $p->search('Search', 'search')->options(['a' => 'Alpha', 'b' => 'Beta'])->default('b');
        $p->search('MultiSearch', 'multisearch')->multiple()->options(['a' => 'Alpha', 'b' => 'Beta'])->default(['b']);
        $p->reorder('Reorder', 'reorder')->options(['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma']);
        $p->confirm('Confirm', 'confirm')->default(TRUE);
        $p->toggle('Toggle', 'toggle')->options(['on' => 'On', 'off' => 'Off'])->default('off');
        $p->filePicker('FilePicker', 'filepicker')->startIn($picker_start);
        $p->filePicker('MultiFilePicker', 'multifilepicker')->multiple()->startIn($picker_start);
        $p->pause('Pause', 'pause');
        $p->progress('Progress', 'progress')->steps(3)->work(static function (ProgressReporter $reporter): void {
          for ($step = 1; $step <= 3; $step++) {
            $reporter->advance('step ' . $step);
          }
        });
      });
  }

}
