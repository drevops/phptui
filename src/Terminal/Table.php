<?php

declare(strict_types=1);

namespace DrevOps\Tui\Terminal;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Utils\Strings;

/**
 * Pure table geometry: column widths and aligned, bordered row assembly.
 *
 * Theme-agnostic, like {@see Box} and {@see Ansi} - it knows nothing about
 * colours. It takes headers and rows of plain strings, sizes each column to its
 * widest cell, and draws a full grid from a {@see Box} character set. Optional
 * styler closures colour the header cells, body cells and border glyphs during
 * assembly, so the width maths always runs on the raw text.
 *
 * @package DrevOps\Tui\Terminal
 */
final class Table {

  /**
   * Render headers and rows as an aligned, bordered table.
   *
   * @param list<string> $headers
   *   The header cells; an empty list draws the grid with no header row.
   * @param list<list<string>> $rows
   *   The body rows. A short row is padded with empty cells and a row with more
   *   cells than the header widens the grid to fit it.
   * @param \DrevOps\Tui\Theme\Border $border
   *   The border style; None shares the single-line glyph set.
   * @param bool $unicode
   *   Whether Unicode glyphs are used; FALSE draws the ASCII fallback.
   * @param int $max_width
   *   The widest the whole table may be, in columns; 0 leaves it uncapped.
   *   When the natural width exceeds it, the widest columns shrink (down to a
   *   single column each) and over-long cells are ellipsized so the borders
   *   stay whole.
   * @param (callable(string): string)|null $style_header
   *   A styler for header cells, or NULL for none.
   * @param (callable(string): string)|null $style_cell
   *   A styler for body cells, or NULL for none.
   * @param (callable(string): string)|null $style_border
   *   A styler for the border glyphs, or NULL for none.
   *
   * @return list<string>
   *   The table's physical lines; empty when there are no columns to draw.
   */
  public static function render(array $headers, array $rows, Border $border, bool $unicode, int $max_width = 0, ?callable $style_header = NULL, ?callable $style_cell = NULL, ?callable $style_border = NULL): array {
    $columns = self::columnCount($headers, $rows);

    if ($columns === 0) {
      return [];
    }

    $norm_headers = $headers === [] ? [] : self::pad($headers, $columns);
    $norm_rows = array_map(static fn(array $row): array => self::pad($row, $columns), $rows);
    $widths = self::fitWidths(self::columnWidths($norm_headers, $norm_rows, $columns), $max_width);

    $chars = Box::chars($border, $unicode);
    $border_styler = $style_border ?? static fn(string $glyphs): string => $glyphs;

    $lines = [self::rule($chars['tl'], $chars['tt'], $chars['tr'], $chars['h'], $widths, $border_styler)];

    if ($norm_headers !== []) {
      $lines[] = self::row($norm_headers, $widths, $chars['v'], $unicode, $style_header, $border_styler);
      $lines[] = self::rule($chars['ml'], $chars['cx'], $chars['mr'], $chars['h'], $widths, $border_styler);
    }

    foreach ($norm_rows as $norm_row) {
      $lines[] = self::row($norm_row, $widths, $chars['v'], $unicode, $style_cell, $border_styler);
    }

    $lines[] = self::rule($chars['bl'], $chars['bt'], $chars['br'], $chars['h'], $widths, $border_styler);

    return $lines;
  }

  /**
   * The column count: the widest of the header and every row.
   *
   * @param list<string> $headers
   *   The header cells.
   * @param list<list<string>> $rows
   *   The body rows.
   *
   * @return int
   *   The number of columns; 0 when there is nothing to draw.
   */
  protected static function columnCount(array $headers, array $rows): int {
    $columns = count($headers);

    foreach ($rows as $row) {
      $columns = max($columns, count($row));
    }

    return $columns;
  }

  /**
   * Pad (or leave) a row to an exact column count with empty trailing cells.
   *
   * @param list<string> $row
   *   The row cells.
   * @param int $columns
   *   The target column count.
   *
   * @return list<string>
   *   The row, exactly the target length.
   */
  protected static function pad(array $row, int $columns): array {
    $out = [];

    for ($index = 0; $index < $columns; $index++) {
      $out[] = self::oneLine($row[$index] ?? '');
    }

    return $out;
  }

  /**
   * Fold a cell's line breaks to spaces so it stays a single physical row.
   *
   * A cell spans exactly one row; an embedded newline would split it and
   * desync the grid's borders and width maths, so CRLF, CR and LF each fold
   * to a single space.
   *
   * @param string $cell
   *   The cell text.
   *
   * @return string
   *   The cell on one line.
   */
  protected static function oneLine(string $cell): string {
    return str_replace(["\r\n", "\r", "\n"], ' ', $cell);
  }

  /**
   * The natural width of each column: its widest cell, header included.
   *
   * @param list<string> $headers
   *   The padded header cells (or an empty list).
   * @param list<list<string>> $rows
   *   The padded body rows.
   * @param int $columns
   *   The column count.
   *
   * @return list<int>
   *   The per-column visible width.
   */
  protected static function columnWidths(array $headers, array $rows, int $columns): array {
    $widths = [];

    for ($column = 0; $column < $columns; $column++) {
      $width = $headers === [] ? 0 : Ansi::width($headers[$column] ?? '');

      foreach ($rows as $row) {
        $width = max($width, Ansi::width($row[$column] ?? ''));
      }

      $widths[] = $width;
    }

    return $widths;
  }

  /**
   * Shrink the widest columns until the table fits a maximum width.
   *
   * Each pass trims one column from the current widest by a single column, so
   * the columns give ground evenly. A column never shrinks below one column;
   * once every column is there the table is left as narrow as it can be, even
   * if that still exceeds the cap.
   *
   * @param list<int> $widths
   *   The natural per-column widths.
   * @param int $max_width
   *   The width cap; 0 or less leaves the widths untouched.
   *
   * @return list<int>
   *   The fitted per-column widths.
   */
  protected static function fitWidths(array $widths, int $max_width): array {
    if ($max_width <= 0 || $widths === []) {
      return $widths;
    }

    $total = array_sum($widths) + 3 * count($widths) + 1;

    while ($total > $max_width) {
      $max = max($widths);

      // Every column is down to a single column; the table cannot narrow
      // further, even if that still overflows the cap.
      if ($max <= 1) {
        break;
      }

      foreach ($widths as $index => $width) {
        if ($width === $max) {
          $widths[$index] = $width - 1;

          break;
        }
      }

      $total--;
    }

    return $widths;
  }

  /**
   * Build a horizontal rule spanning every column, with the column junctions.
   *
   * @param string $left
   *   The left corner or junction glyph.
   * @param string $mid
   *   The between-column junction glyph.
   * @param string $right
   *   The right corner or junction glyph.
   * @param string $fill
   *   The horizontal fill glyph.
   * @param list<int> $widths
   *   The per-column widths.
   * @param callable(string): string $styler
   *   The border styler.
   *
   * @return string
   *   The styled rule.
   */
  protected static function rule(string $left, string $mid, string $right, string $fill, array $widths, callable $styler): string {
    $segments = [];

    foreach ($widths as $width) {
      $segments[] = str_repeat($fill, $width + 2);
    }

    return $styler($left . implode($mid, $segments) . $right);
  }

  /**
   * Build one table row: bordered, gutter-padded, styled cells.
   *
   * @param list<string> $cells
   *   The row's cells, already padded to the column count.
   * @param list<int> $widths
   *   The per-column widths.
   * @param string $vertical
   *   The vertical border glyph.
   * @param bool $unicode
   *   Whether Unicode glyphs are used (an over-long cell is ellipsized).
   * @param (callable(string): string)|null $cell_styler
   *   The cell styler, or NULL for none.
   * @param callable(string): string $border_styler
   *   The border styler.
   *
   * @return string
   *   The composed row.
   */
  protected static function row(array $cells, array $widths, string $vertical, bool $unicode, ?callable $cell_styler, callable $border_styler): string {
    $bar = $border_styler($vertical);
    $out = $bar;

    foreach ($widths as $index => $width) {
      $cell = self::fitCell($cells[$index] ?? '', $width, $unicode);
      $out .= ' ' . ($cell_styler === NULL ? $cell : $cell_styler($cell)) . ' ' . $bar;
    }

    return $out;
  }

  /**
   * Fit a cell to a column width: clip (ellipsized) if too wide, else pad.
   *
   * @param string $content
   *   The cell content.
   * @param int $width
   *   The column width.
   * @param bool $unicode
   *   Whether Unicode glyphs are used; an ASCII clip has no ellipsis glyph and
   *   hard-clips instead.
   *
   * @return string
   *   The content fitted to exactly the column width.
   */
  protected static function fitCell(string $content, int $width, bool $unicode): string {
    if ($width <= 0) {
      return '';
    }

    $visible = Ansi::width($content);

    if ($visible > $width) {
      $content = $unicode
        ? Strings::substr(Ansi::strip($content), 0, $width - 1) . '…'
        : Strings::substr(Ansi::strip($content), 0, $width);
      $visible = Ansi::width($content);
    }

    return $content . str_repeat(' ', max(0, $width - $visible));
  }

}
