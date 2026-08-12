<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Terminal;

use DrevOps\PhpTui\Terminal\Table;
use DrevOps\PhpTui\Theme\Border;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure table geometry helper.
 */
#[CoversClass(Table::class)]
#[Group('terminal')]
final class TableTest extends TestCase {

  public function testRendersAlignedGrid(): void {
    $expected = [
      '┌───────┬────────┐',
      '│ Fruit │ Colour │',
      '├───────┼────────┤',
      '│ Apple │ Red    │',
      '│ Pear  │ Green  │',
      '└───────┴────────┘',
    ];

    $this->assertSame($expected, Table::render(['Fruit', 'Colour'], [['Apple', 'Red'], ['Pear', 'Green']], Border::Line, TRUE));
  }

  public function testRendersAsciiFallback(): void {
    $expected = [
      '+-------+--------+',
      '| Fruit | Colour |',
      '+-------+--------+',
      '| Apple | Red    |',
      '| Pear  | Green  |',
      '+-------+--------+',
    ];

    $this->assertSame($expected, Table::render(['Fruit', 'Colour'], [['Apple', 'Red'], ['Pear', 'Green']], Border::Line, FALSE));
  }

  public function testHonoursBorderStyle(): void {
    $lines = Table::render(['A', 'B'], [['c', 'd']], Border::Double, TRUE);

    // The double set carries its own corners, tees and cross.
    $this->assertStringContainsString('╔', $lines[0]);
    $this->assertStringContainsString('╦', $lines[0]);
    $this->assertStringContainsString('╬', $lines[2]);
    $this->assertStringContainsString('╩', $lines[4]);
  }

  public function testRaggedRowsPadAndWidenTheGrid(): void {
    // The short row pads to three cells; the long row widens the grid to fit.
    $expected = [
      '┌────┬────┬───┐',
      '│ H1 │ H2 │   │',
      '├────┼────┼───┤',
      '│ A  │    │   │',
      '│ B  │ C  │ D │',
      '└────┴────┴───┘',
    ];

    $this->assertSame($expected, Table::render(['H1', 'H2'], [['A'], ['B', 'C', 'D']], Border::Line, TRUE));
  }

  public function testEmptyHeadersDrawNoHeaderRow(): void {
    $expected = [
      '┌───┬───┐',
      '│ A │ B │',
      '└───┴───┘',
    ];

    $lines = Table::render([], [['A', 'B']], Border::Line, TRUE);

    $this->assertSame($expected, $lines);
    // No header means no header-separator rule.
    $this->assertStringNotContainsString('├', implode("\n", $lines));
  }

  public function testEmptyRowsDrawHeaderOnly(): void {
    $expected = [
      '┌──────┐',
      '│ Only │',
      '├──────┤',
      '└──────┘',
    ];

    $this->assertSame($expected, Table::render(['Only'], [], Border::Line, TRUE));
  }

  public function testNoColumnsRendersNothing(): void {
    $this->assertSame([], Table::render([], [], Border::Line, TRUE));
    $this->assertSame([], Table::render([], [[]], Border::Line, TRUE));
  }

  public function testEmptyColumnStillDrawsItsGutter(): void {
    // A column that is empty throughout has zero content width; it renders as
    // the bare one-column gutter each side.
    $expected = [
      '┌──┬───┐',
      '│  │ X │',
      '└──┴───┘',
    ];

    $this->assertSame($expected, Table::render([], [['', 'X']], Border::Line, TRUE));
  }

  public function testStylersWrapEachSegment(): void {
    $lines = Table::render(
      ['H'],
      [['x']],
      Border::Line,
      TRUE,
      0,
      static fn(string $cell): string => '<h>' . $cell . '</h>',
      static fn(string $cell): string => '<c>' . $cell . '</c>',
      static fn(string $glyphs): string => '<b>' . $glyphs . '</b>',
    );
    $joined = implode("\n", $lines);

    $this->assertStringContainsString('<b>┌───┐</b>', $joined);
    $this->assertStringContainsString('<h>H</h>', $joined);
    $this->assertStringContainsString('<c>x</c>', $joined);
    $this->assertStringContainsString('<b>│</b>', $joined);
  }

  public function testOverflowShrinksAndEllipsizes(): void {
    // The single column cannot fit its cell in the cap, so it shrinks and the
    // cell is ellipsized rather than clipping the right border away.
    $expected = [
      '┌──────┐',
      '│ Name │',
      '├──────┤',
      '│ Wat… │',
      '└──────┘',
    ];

    $this->assertSame($expected, Table::render(['Name'], [['Watermelon']], Border::Line, TRUE, 8));
  }

  public function testOverflowHardClipsInAsciiMode(): void {
    $lines = Table::render(['Name'], [['Watermelon']], Border::Line, FALSE, 8);
    $joined = implode("\n", $lines);

    // ASCII has no ellipsis glyph, so an over-long cell is hard-clipped.
    $this->assertStringContainsString('| Wate |', $joined);
    $this->assertStringNotContainsString('…', $joined);
  }

  public function testFoldsCellLineBreaks(): void {
    // A cell that carries a line break stays one physical row, so the grid's
    // borders never desync - CRLF, CR and LF each fold to a space.
    $lines = Table::render(['H'], [["a\nb"], ["c\r\nd"], ["e\rf"]], Border::Line, TRUE);

    $this->assertCount(7, $lines);
    $this->assertStringContainsString('a b', $lines[3]);
    $this->assertStringContainsString('c d', $lines[4]);
    $this->assertStringContainsString('e f', $lines[5]);

    foreach ($lines as $line) {
      $this->assertStringNotContainsString("\n", $line);
    }
  }

  public function testOverflowFloorsColumnsAtOneColumn(): void {
    // Too many columns for the cap: every column shrinks to a single column and
    // the table is left as narrow as it can be, each cell an ellipsis.
    $lines = Table::render(['AA', 'BB'], [['CC', 'DD']], Border::Line, TRUE, 3);

    $this->assertCount(5, $lines);
    $this->assertSame('│ … │ … │', $lines[1]);
    $this->assertSame('│ … │ … │', $lines[3]);
  }

}
