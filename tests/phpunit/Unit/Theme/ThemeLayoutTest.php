<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Spacing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the panel-grid layout rendering and measurement.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ThemeLayoutTest extends TestCase {

  public function testLayoutZipsPanelsSideBySide(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]);

    [$lines] = $theme->renderBody($this->grid([2]), new Answers(['one' => 'apple', 'two' => 'carrot'], []), 0);

    // Both blocks share the rows: titles beside each other, values beneath.
    $this->assertStringContainsString('A >', $lines[0]);
    $this->assertStringContainsString('B >', $lines[0]);
    $this->assertStringContainsString('One  apple', $lines[1]);
    $this->assertStringContainsString('Two  carrot', $lines[1]);
  }

  public function testContentWidthReflectsBorderInset(): void {
    // A borderless frame keeps the full width for content.
    $this->assertSame(80, (new DefaultTheme(80, ['border' => Border::None]))->contentWidth());
    // A bordered frame reserves the border and gutter columns.
    $this->assertSame(76, (new DefaultTheme(80, ['border' => Border::Line]))->contentWidth());
  }

  public function testLayoutStacksRowsWithBlankLineBetween(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]);

    [$lines] = $theme->renderBody($this->grid([1, 2]), new Answers(), 0);
    $body = array_map(Ansi::strip(...), $lines);

    // Row one holds A alone; a blank line separates it from B and C.
    $this->assertStringContainsString('A >', $body[0]);
    $this->assertStringNotContainsString('B >', $body[0]);
    $this->assertSame('', $body[2]);
    $this->assertStringContainsString('B >', $body[3]);
    $this->assertStringContainsString('C >', $body[3]);
  }

  public function testLayoutCursorLineTracksTheSelectedRow(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]);
    $panel = $this->grid([1, 2]);

    // The third panel sits on the second grid row: title (A), value row,
    // blank, then the row it starts on.
    [, $first] = $theme->renderBody($panel, new Answers(), 0);
    [$lines, $third] = $theme->renderBody($panel, new Answers(), 2);

    $this->assertSame(0, $first);
    $this->assertSame(3, $third);
    // The selected block carries the marker.
    $this->assertStringContainsString('> C', Ansi::strip($lines[3]));
  }

  public function testLayoutRendersFieldsAboveTheGrid(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]);
    $field = new Field('note', 'Note', '', FieldType::Text, '');
    $panel = new Panel('p', 'P', '', [$field], $this->grid([2])->panels, NULL, [2]);

    [$lines] = $theme->renderBody($panel, new Answers(['note' => 'ripe'], []), 0);
    $body = array_map(Ansi::strip(...), $lines);

    // The field keeps its normal row above the grid, separated by a blank.
    $this->assertStringContainsString('Note  ripe', $body[0]);
    $this->assertSame('', $body[1]);
    $this->assertStringContainsString('A >', $body[2]);
    $this->assertStringContainsString('B >', $body[2]);
  }

  public function testLayoutBlockShowsDescriptionAndDrillInRows(): void {
    $theme = new DefaultTheme(60, ['color' => FALSE, 'unicode' => FALSE]);
    $inner = new Panel('inner', 'Inner', '', [new Field('deep', 'Deep', '', FieldType::Text, '')]);
    $panel = new Panel('p', 'P', '', [], [
      new Panel('a', 'A', 'Fresh from the stall.', [], [$inner]),
      new Panel('b', 'B', '', [new Field('two', 'Two', '', FieldType::Text, '')]),
    ], NULL, [2]);

    [$lines] = $theme->renderBody($panel, new Answers(), 0);
    $body = implode("\n", $lines);

    // The description sits under the title and the nested panel shows as a
    // drill-in row.
    $this->assertStringContainsString('Fresh from the stall.', $body);
    $this->assertStringContainsString('Inner >', $body);
  }

  public function testLayoutClipsBlocksToTheColumnWidth(): void {
    $theme = new DefaultTheme(30, ['color' => FALSE, 'unicode' => FALSE]);
    $panel = new Panel('p', 'P', '', [], [
      new Panel('a', 'A rather long panel title indeed', ''),
      new Panel('b', 'B', ''),
    ], NULL, [2]);

    [$lines] = $theme->renderBody($panel, new Answers(), 0);

    // Two columns in 30 columns leave 14 each: the long title clips, and the
    // second column still starts at its own edge.
    $this->assertLessThanOrEqual(30, Ansi::width($lines[0]));
    $this->assertStringContainsString('B', $lines[0]);
  }

  public function testLayoutClampsTheAssembledRowToTinyFrames(): void {
    // Three one-column cells plus two gutters outgrow a five-column frame;
    // the assembled row is clamped as a whole.
    $theme = new DefaultTheme(5, ['color' => FALSE, 'unicode' => FALSE]);
    $panel = new Panel('p', 'P', '', [], [
      new Panel('a', 'A', ''),
      new Panel('b', 'B', ''),
      new Panel('c', 'C', ''),
    ], NULL, [3]);

    [$lines] = $theme->renderBody($panel, new Answers(), 0);

    foreach ($lines as $line) {
      $this->assertLessThanOrEqual(5, Ansi::width($line));
    }
  }

  public function testLayoutPreviewsMultiLineValuesAsTheirFirstLine(): void {
    $theme = new DefaultTheme(60, ['color' => FALSE, 'unicode' => FALSE]);
    $panel = new Panel('p', 'P', '', [], [
      new Panel('a', 'A', '', [new Field('notes', 'Notes', '', FieldType::Textarea, '')]),
      new Panel('b', 'B', '', [new Field('two', 'Two', '', FieldType::Text, '')]),
    ], NULL, [2]);

    [$lines] = $theme->renderBody($panel, new Answers(['notes' => "Crisp and sweet\nHint of citrus", 'two' => 'x'], []), 0);

    // A grid cell is one physical row: the multi-line value previews as its
    // first line with an ellipsis, and no entry carries an embedded newline
    // that would desync the column zip.
    $body = implode('|', $lines);
    $this->assertStringContainsString('Crisp and sweet…', $body);
    $this->assertStringNotContainsString('Hint of citrus', $body);
    $this->assertStringNotContainsString("\n", $body);
    $this->assertCount(2, $lines);
  }

  public function testMeasureUsesTheWidestValueLine(): void {
    $form = Form::create('T')
      ->buttons(FALSE)
      ->panel('p', 'P', function (PanelBuilder $p): void {
        $p->textarea('notes', 'A')->default("Crisp and sweet\nlonger second line");
      })
      ->build();

    // The two value lines stack under the value column, so the row needs the
    // widest single line (18), never the whole string's length.
    $answers = new Answers(['notes' => "Crisp and sweet\nlonger second line"], []);
    $this->assertSame(23, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, $answers));
  }

  public function testMeasureContentWidthCoversTheGrid(): void {
    $form = Form::create('T')
      ->layout(2)
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->text('one', 'Preferred window')->default('Morning'))
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
      ->build();

    // The widest block is A: field row 4 + 16 + 7 = 27. Two equal columns
    // need 27 * 2 + 2 = 56 - wider than any single linear row.
    $answers = new Answers(['one' => 'Morning'], []);
    $this->assertSame(56, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, $answers));
  }

  public function testLayoutPreviewsNoteAsItsTitle(): void {
    $theme = new DefaultTheme(60, ['color' => FALSE, 'unicode' => FALSE]);
    $panel = new Panel('p', 'P', '', [], [
      new Panel('a', 'A', '', [new Field('intro', 'Getting started', 'Body text.', FieldType::Note, '')]),
      new Panel('b', 'B', '', [new Field('two', 'Two', '', FieldType::Text, '')]),
    ], NULL, [2]);

    [$lines] = $theme->renderBody($panel, new Answers(), 0);

    // A note in a grid column previews as its title.
    $this->assertStringContainsString('Getting started', implode("\n", $lines));
  }

  public function testMeasureContentWidthCoversNoteInGrid(): void {
    $form = Form::create('T')
      ->buttons(FALSE)
      ->layout(2)
      ->panel('a', 'A', fn(PanelBuilder $p): FieldBuilder => $p->note('intro', 'A fairly long note title here'))
      ->panel('b', 'B', fn(PanelBuilder $p): FieldBuilder => $p->text('two', 'Two'))
      ->build();

    // Block A is driven by the note title: 2 + 29 = 31. Two equal columns need
    // 31 * 2 + 2 = 64. Measuring panel A on its own also walks the note row.
    $this->assertSame(64, (new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->measureContentWidth($form, new Answers()));
  }

  /**
   * A panel whose lettered sub-panels are arranged by the given layout.
   *
   * @param list<int> $layout
   *   The grid rows.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The panel: sub-panels A, B, C... to fill the layout, one text field
   *   each.
   */
  protected function grid(array $layout): Panel {
    $panels = [];
    $ids = ['one', 'two', 'three', 'four'];

    for ($index = 0; $index < array_sum($layout); $index++) {
      $letter = chr(ord('A') + $index);
      $panels[] = new Panel(strtolower($letter), $letter, '', [new Field($ids[$index], ucfirst($ids[$index]), '', FieldType::Text, '')]);
    }

    return new Panel('p', 'P', '', [], $panels, NULL, $layout);
  }

}
