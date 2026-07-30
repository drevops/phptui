<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Testing;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Tests\Fixtures\Form\AllFieldsForm;
use DrevOps\Tui\Tests\Fixtures\Theme\OceanTheme;
use DrevOps\Tui\Theme\Mode;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Drives every field through the harness and guards field-type coverage.
 */
#[CoversNothing]
#[Group('testing')]
final class AllFieldsFormTest extends TestCase {

  /**
   * The directory the file-picker fields open at.
   */
  protected string $pick;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    vfsStream::setup('root', NULL, ['pick' => ['file.txt' => '']]);
    $this->pick = vfsStream::url('root/pick');
  }

  public function testEveryFieldTypeIsExercised(): void {
    $form = AllFieldsForm::create()->build();

    $present = [];
    foreach ($form->fields() as $field) {
      $present[$field->type->value] = TRUE;
    }
    $actual = array_keys($present);
    sort($actual);

    $expected = array_map(static fn(FieldType $type): string => $type->value, FieldType::cases());
    sort($expected);

    $this->assertSame($expected, $actual, 'AllFieldsForm must exercise every FieldType so new fields are not left untested.');
  }

  public function testDrivesEveryField(): void {
    $tester = new TuiTester(AllFieldsForm::create($this->pick));

    $answers = $tester->run(...$this->keystrokes());

    $this->assertSame('txt', $answers->value('text'));
    $this->assertSame('a-b', $answers->value('template'));
    $this->assertSame(['head' => 'a', 'tail' => 'b'], $answers->parts('template'));
    $this->assertSame(7, $answers->value('number'));
    $this->assertSame(4, $answers->value('rating'));
    $this->assertSame('2026-07-15', $answers->value('date'));
    $this->assertSame('note', $answers->value('textarea'));
    $this->assertSame('pw', $answers->value('password'));
    $this->assertSame('b', $answers->value('select'));
    $this->assertSame(['a'], $answers->value('multiselect'));
    $this->assertSame('utc', $answers->value('suggest'));
    $this->assertSame('b', $answers->value('search'));
    $this->assertSame(['b'], $answers->value('multisearch'));
    $this->assertSame(['a', 'b', 'c'], $answers->value('reorder'));
    $this->assertTrue($answers->value('confirm'));
    $this->assertSame('off', $answers->value('toggle'));
    $this->assertSame($this->pick . '/file.txt', $answers->value('filepicker'));
    $this->assertSame([$this->pick . '/file.txt'], $answers->value('multifilepicker'));
    $this->assertTrue($answers->value('pause'));
    $this->assertFalse($tester->isCancelled());
  }

  #[DataProvider('dataProviderRendersEveryFieldAcrossThemes')]
  public function testRendersEveryFieldAcrossThemes(string $theme, array $options): void {
    $tester = (new TuiTester(AllFieldsForm::create($this->pick)))->theme($theme)->options($options);

    $answers = $tester->run(...$this->keystrokes());

    // Answers are collected identically regardless of the theme.
    $this->assertSame('txt', $answers->value('text'));
    $this->assertSame('off', $answers->value('toggle'));

    // Every field's label was rendered somewhere in the session.
    $display = $tester->display();
    foreach (['Note', 'Text', 'Template', 'Number', 'Rating', 'Calendar', 'Textarea', 'Password', 'Select', 'MultiSelect', 'Suggest', 'Search', 'MultiSearch', 'Reorder', 'Confirm', 'Toggle', 'FilePicker', 'MultiFilePicker', 'Pause', 'Progress'] as $label) {
      $this->assertStringContainsString($label, $display, sprintf('The "%s" label was not rendered.', $label));
    }
  }

  public static function dataProviderRendersEveryFieldAcrossThemes(): \Iterator {
    yield 'default dark' => ['', ['mode' => Mode::Dark, 'color' => TRUE, 'unicode' => TRUE]];
    yield 'default light' => ['', ['mode' => Mode::Light, 'color' => TRUE, 'unicode' => TRUE]];
    yield 'ascii no color' => ['', ['mode' => Mode::Dark, 'color' => FALSE, 'unicode' => FALSE]];
    yield 'custom theme class' => [OceanTheme::class, ['mode' => Mode::Dark, 'color' => TRUE, 'unicode' => TRUE]];
    // Each curated built-in theme renders every field; ember drives the
    // no-colour (no-ANSI) path across all of them.
    yield 'midnight dark' => ['midnight', ['mode' => Mode::Dark, 'color' => TRUE, 'unicode' => TRUE]];
    yield 'frost light' => ['frost', ['mode' => Mode::Light, 'color' => TRUE, 'unicode' => TRUE]];
    yield 'ember no color' => ['ember', ['mode' => Mode::Dark, 'color' => FALSE, 'unicode' => FALSE]];
    yield 'mono light' => ['mono', ['mode' => Mode::Light, 'color' => TRUE, 'unicode' => TRUE]];
    yield 'dos dark' => ['dos', ['mode' => Mode::Dark, 'color' => TRUE, 'unicode' => TRUE]];
  }

  /**
   * The scripted keystrokes that open and accept every field, then submit.
   *
   * @return list<string|\DrevOps\Tui\Input\Key>
   *   The keystrokes.
   */
  protected function keystrokes(): array {
    $enter = Key::named(KeyName::Enter);
    $down = Key::named(KeyName::Down);
    $tab = Key::named(KeyName::Tab);
    $space = Key::named(KeyName::Space);
    $escape = Key::named(KeyName::Escape);

    return [
      // Drill into the Fields panel; the cursor lands on the first field.
      $enter,
      // Each field: open the editor, accept its default, move to the next.
      $enter, $enter, $down,
      // Template: open, accept the filled-in default.
      $enter, $enter, $down,
      $enter, $enter, $down,
      // Rating: open, accept the point the default sits on.
      $enter, $enter, $down,
      // Calendar accepts the current day with Enter.
      $enter, $enter, $down,
      // Textarea accepts with Tab (Enter inserts a newline).
      $enter, $tab, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      // Reorder: open, accept the default (declared) ranking.
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      $enter, $enter, $down,
      // Multi file picker: open, toggle the highlighted entry, accept.
      $enter, $space, $enter, $down,
      // Pause: open and acknowledge, then move to the progress row.
      $enter, $enter, $down,
      // Progress: activate to run its work (it collects no value).
      $enter,
      // Back to the root, then activate Submit.
      $escape, $down, $enter,
    ];
  }

}
