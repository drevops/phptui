<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the opt-in indentation of conditional fields.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ThemeConditionalIndentTest extends TestCase {

  /**
   * The columns one condition of nesting steps a field in by.
   */
  protected const int STEP = 2;

  public function testOptionIsOffByDefault(): void {
    $panel = $this->chainPanel();

    [$lines] = $this->theme(FALSE)->renderBody($panel, new Answers(), 0);

    // The cursor row leads with the marker glyph; every other row leads with
    // the two blank columns that stand in for it, and nothing more.
    $this->assertSame(0, $this->leadingSpaces($lines[0]));
    $this->assertSame([2, 2, 2], array_map($this->leadingSpaces(...), array_slice($lines, 1)));
  }

  public function testEachConditionStepsTheRowInFurther(): void {
    $panel = $this->chainPanel();

    [$lines] = $this->theme()->renderBody($panel, new Answers(), 0);

    // The cursor sits on the unconditional root, so its marker shows; the
    // conditional rows carry the blank marker plus one step per condition.
    $this->assertSame('❯ Root  ', Ansi::strip($lines[0]));
    $this->assertSame([
      2 + self::STEP,
      2 + self::STEP * 2,
      2 + self::STEP * 3,
    ], array_map($this->leadingSpaces(...), array_slice($lines, 1)));
  }

  public function testIndentedRowKeepsBadgeAtFrameEdge(): void {
    $field = $this->fieldsOf($this->chainPanel())['first'];
    $answers = new Answers(['first' => 'Acme'], ['first' => Provenance::Edited]);

    $lines = $this->theme()->renderFieldLine($field, $answers, FALSE);

    $this->assertStringContainsString('First  Acme', Ansi::strip($lines[0]));
    $this->assertStringContainsString('edited', Ansi::strip($lines[0]));
    // The gutter sits inside the row, so the badge still lands on the frame's
    // right edge rather than being pushed past it.
    $this->assertSame(40, Ansi::width($lines[0]));
  }

  public function testValueContinuationLinesFollowTheIndentedColumn(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('notes', 'Notes', '', FieldType::Textarea, '', when: new Condition('root', eq: 'x')),
    ]);
    $form = new FormDefinition('T', 'S', [$panel]);
    $answers = new Answers(['notes' => "Crisp and sweet\nHint of citrus"], []);

    $lines = $this->theme()->renderFieldLine($this->fieldsOf($form->panels[0])['notes'], $answers, FALSE);

    $this->assertSame('    Notes  Crisp and sweet', Ansi::strip($lines[0]));
    // The second line aligns under the value column, which the gutter moved.
    $this->assertSame($this->columnOf($lines[0], 'Crisp'), $this->columnOf($lines[1], 'Hint'));
  }

  public function testDescriptionStepsInWithItsField(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', 'root help', FieldType::Text, ''),
      new Field('leaf', 'Leaf', 'leaf help', FieldType::Text, '', when: new Condition('root', eq: 'x')),
    ]);
    new FormDefinition('T', 'S', [$panel]);

    [$lines] = $this->theme()->renderBody($panel, new Answers(), 0);
    $rows = array_map(Ansi::strip(...), $lines);

    // A description already sits four columns in; the conditional one carries
    // its field's gutter on top of that.
    $this->assertSame(4, $this->leadingSpaces($rows[1]));
    $this->assertSame(4 + self::STEP, $this->leadingSpaces($rows[3]));
  }

  public function testInlineEditorOpensAtTheIndentedColumn(): void {
    $field = $this->fieldsOf($this->chainPanel())['second'];

    $lines = $this->theme()->renderInlineEditor($field, "line one\nline two", TRUE);

    $this->assertSame('    ❯ Second  line one', Ansi::strip($lines[0]));
    $this->assertSame($this->columnOf($lines[0], 'line one'), $this->columnOf($lines[1], 'line two'));
  }

  public function testPlainNoteCardStepsInWithItsCondition(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('hint', 'Storage', 'Keep it cool.', FieldType::Note, '', when: new Condition('root', eq: 'x')),
    ]);
    new FormDefinition('T', 'S', [$panel]);

    $lines = array_map(Ansi::strip(...), $this->theme()->renderNoteLines($this->fieldsOf($panel)['hint'], new Answers()));

    // A card already sits in a two-column gutter of its own.
    $this->assertSame('    Storage', $lines[0]);
    $this->assertSame('    Keep it cool.', $lines[1]);
  }

  public function testBorderedNoteNarrowsToStayInsideTheFrame(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('hint', 'Storage', str_repeat('long ', 30), FieldType::Note, '', when: new Condition('root', eq: 'x'), bordered: TRUE),
    ]);
    new FormDefinition('T', 'S', [$panel]);
    $note = $this->fieldsOf($panel)['hint'];

    $flush = $this->theme(FALSE)->renderNoteLines($note, new Answers());
    $stepped = $this->theme()->renderNoteLines($note, new Answers());

    // The box loses exactly the columns the gutter takes, so its right edge
    // stays where the flush one put it.
    $this->assertSame(40, Ansi::width($flush[0]));
    $this->assertSame(40, Ansi::width($stepped[0]));
    $this->assertSame(self::STEP, $this->leadingSpaces(Ansi::strip($stepped[0])));
  }

  public function testMeasuredWidthCoversTheIndent(): void {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('leaf', 'A rather long conditional label', '', FieldType::Text, '', when: new Condition('root', eq: 'x')),
    ]);
    $form = new FormDefinition('T', 'S', [$panel], buttons: new Buttons(show: FALSE));

    $flush = $this->theme(FALSE)->measureContentWidth($form, new Answers());
    $stepped = $this->theme()->measureContentWidth($form, new Answers());

    $this->assertSame($flush + self::STEP, $stepped);
  }

  public function testGridColumnPreviewStepsInToo(): void {
    $sub = new Panel('sub', 'Sub', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('leaf', 'Leaf', '', FieldType::Text, '', when: new Condition('root', eq: 'x')),
    ]);
    $panel = new Panel('p', 'P', '', [], [$sub], layout: [1]);
    new FormDefinition('T', 'S', [$panel]);

    [$lines] = $this->theme()->renderBody($panel, new Answers(), -1);
    $rows = array_map(Ansi::strip(...), $lines);

    $this->assertSame(2, $this->leadingSpaces($rows[1]), 'An unconditional preview row keeps the block gutter.');
    $this->assertSame(2 + self::STEP, $this->leadingSpaces($rows[2]));
  }

  /**
   * Tests that the option takes a boolean and nothing else.
   *
   * @param mixed $value
   *   The value passed as the "indent_conditional" option.
   */
  #[DataProvider('dataProviderRejectsNonBooleanOption')]
  public function testRejectsNonBooleanOption(mixed $value): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "indent_conditional"');

    new DefaultTheme(40, ['indent_conditional' => $value]);
  }

  public static function dataProviderRejectsNonBooleanOption(): \Iterator {
    yield 'a string' => ['yes'];
    yield 'a column count' => [2];
    yield 'nothing' => [NULL];
  }

  /**
   * A panel whose fields form a three-link condition chain.
   *
   * Registering it with a form definition is what resolves the depths, so the
   * panel is returned already stamped.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The panel.
   */
  protected function chainPanel(): Panel {
    $panel = new Panel('p', 'P', '', [
      new Field('root', 'Root', '', FieldType::Text, ''),
      new Field('first', 'First', '', FieldType::Text, '', when: new Condition('root', eq: 'x')),
      new Field('second', 'Second', '', FieldType::Text, '', when: new Condition('first', eq: 'y')),
      new Field('third', 'Third', '', FieldType::Text, '', when: new Condition('second', eq: 'z')),
    ]);

    new FormDefinition('T', 'S', [$panel]);

    return $panel;
  }

  /**
   * A panel's fields keyed by id.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel.
   *
   * @return array<string,\DrevOps\Tui\Model\Field>
   *   The fields.
   */
  protected function fieldsOf(Panel $panel): array {
    $fields = [];

    foreach ($panel->fields as $field) {
      $fields[$field->id] = $field;
    }

    return $fields;
  }

  /**
   * The number of blank columns a row opens with.
   *
   * @param string $line
   *   The rendered row.
   *
   * @return int
   *   The blank column count.
   */
  protected function leadingSpaces(string $line): int {
    $plain = Ansi::strip($line);

    return Ansi::width($plain) - Ansi::width(ltrim($plain));
  }

  /**
   * The screen column a fragment starts at within a row.
   *
   * Byte offsets would disagree between a row carrying the multi-byte cursor
   * marker and one padded with plain blanks, so the fragment is located by the
   * width of what precedes it.
   *
   * @param string $line
   *   The rendered row.
   * @param string $needle
   *   The fragment to locate.
   *
   * @return int
   *   The zero-based column.
   */
  protected function columnOf(string $line, string $needle): int {
    $plain = Ansi::strip($line);
    $offset = mb_strpos($plain, $needle);

    $this->assertIsInt($offset, sprintf('"%s" is present in "%s".', $needle, $plain));

    return Ansi::width(mb_substr($plain, 0, $offset));
  }

  /**
   * A colourless borderless theme of fixed width.
   *
   * @param bool $indent
   *   Whether conditional fields are indented.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(bool $indent = TRUE): DefaultTheme {
    return new DefaultTheme(40, [
      'color' => FALSE,
      'border' => Border::None,
      'spacing' => Spacing::Normal,
      'indent_conditional' => $indent,
    ]);
  }

}
