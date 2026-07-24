<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Form;

use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Primitive\ProgressReporter;

/**
 * Test fixture: a form exercising one field of every widget type.
 *
 * Every FieldType is represented exactly once so the panel TUI can be driven
 * through each widget in a single run; a guard test asserts the coverage stays
 * complete as widget types are added.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Form
 */
final class AllWidgetsForm {

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
    return Form::create('All widgets')
      ->panel('widgets', 'Widgets', function (PanelBuilder $p) use ($picker_start): void {
        $p->text('text', 'Text')->default('txt');
        $p->number('number', 'Number')->default(7);
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
        $p->progress('progress', 'Progress')->steps(3)->run(static function (ProgressReporter $reporter): void {
          for ($step = 0; $step < 3; $step++) {
            $reporter->advance();
          }
        });
      });
  }

}
