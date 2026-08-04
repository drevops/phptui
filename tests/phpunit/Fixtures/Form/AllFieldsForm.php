<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Form;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Primitive\ProgressReporter;

/**
 * Test fixture: a form exercising one field of every field type.
 *
 * Every FieldType is represented exactly once so the panel TUI can be driven
 * through each field in a single run; a guard test asserts the coverage stays
 * complete as field types are added.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Form
 */
final class AllFieldsForm {

  /**
   * Build the fixture form.
   *
   * @param string $picker_start
   *   The directory the file-picker fields open at (a controlled directory so
   *   the browser lands on a known entry).
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  public static function create(string $picker_start = ''): Form {
    return Form::create('All fields')
      ->panel('fields', 'Fields', function (PanelBuilder $p) use ($picker_start): void {
        $p->note('note', 'Note')->body('A read-only note field.');
        $p->text('text', 'Text')->default('txt');
        $p->template('template', 'Template')->pattern('{{head}}-{{tail}}')->default('a-b');
        $p->number('number', 'Number')->default(7);
        $p->rating('rating', 'Rating')->captions([1 => 'Poor', 5 => 'Excellent'])->default(4);
        $p->calendar('date', 'Calendar')->default('2026-07-15');
        $p->textarea('textarea', 'Textarea')->default('note');
        $p->password('password', 'Password')->default('pw');
        $p->select('select', 'Select')->options(['a' => 'Alpha', 'b' => 'Beta'])->default('b');
        $p->select('multiselect', 'MultiSelect')->multiple()->options(['a' => 'Alpha', 'b' => 'Beta'])->default(['a']);
        $p->suggest('suggest', 'Suggest')->options(['utc' => 'UTC', 'gmt' => 'GMT'])->default('utc');
        $p->search('search', 'Search')->options(['a' => 'Alpha', 'b' => 'Beta'])->default('b');
        $p->search('multisearch', 'MultiSearch')->multiple()->options(['a' => 'Alpha', 'b' => 'Beta'])->default(['b']);
        $p->reorder('reorder', 'Reorder')->options(['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma']);
        $p->confirm('confirm', 'Confirm')->default(TRUE);
        $p->toggle('toggle', 'Toggle')->options(['on' => 'On', 'off' => 'Off'])->default('off');
        $p->filePicker('filepicker', 'FilePicker')->startIn($picker_start);
        $p->filePicker('multifilepicker', 'MultiFilePicker')->multiple()->startIn($picker_start);
        $p->pause('pause', 'Pause');
        $p->progress('progress', 'Progress')->steps(3)->work(static function (ProgressReporter $reporter): void {
          for ($step = 1; $step <= 3; $step++) {
            $reporter->advance('step ' . $step);
          }
        });
      });
  }

}
