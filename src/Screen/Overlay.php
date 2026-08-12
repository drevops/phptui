<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\VAlign;
use DrevOps\Tui\Utils\Strings;

/**
 * Pure line-compositor: splice a box of lines over a backdrop, centered.
 *
 * Theme-agnostic: it handles no colour. It places a rendered box over a
 * rectangular backdrop and splices each box line into the backdrop by visible
 * column, so the backdrop shows through the padding on every side. Only the
 * backdrop is sliced - it is plain text the caller has already flattened and
 * padded to the composite width - while the styled box lines are placed
 * verbatim. A caller styles the exposed backdrop (typically dimming it) through
 * the supplied callback.
 *
 * @package DrevOps\Tui\Screen
 */
final class Overlay {

  /**
   * The top-left offset that centers a box within an area.
   *
   * @param int $area_columns
   *   The area's width in columns.
   * @param int $area_rows
   *   The area's height in rows.
   * @param int $box_width
   *   The box's width in columns.
   * @param int $box_height
   *   The box's height in rows.
   *
   * @return array{int,int}
   *   The [top, left] offsets, never negative.
   */
  public static function center(int $area_columns, int $area_rows, int $box_width, int $box_height): array {
    return self::place($area_columns, $area_rows, $box_width, $box_height, HAlign::Center, VAlign::Middle);
  }

  /**
   * The top-left offset that places a box within an area by alignment.
   *
   * @param int $area_columns
   *   The area's width in columns.
   * @param int $area_rows
   *   The area's height in rows.
   * @param int $box_width
   *   The box's width in columns.
   * @param int $box_height
   *   The box's height in rows.
   * @param \DrevOps\Tui\Theme\HAlign $halign
   *   The horizontal alignment of the box within the area.
   * @param \DrevOps\Tui\Theme\VAlign $valign
   *   The vertical alignment of the box within the area.
   *
   * @return array{int,int}
   *   The [top, left] offsets, never negative.
   */
  public static function place(int $area_columns, int $area_rows, int $box_width, int $box_height, HAlign $halign, VAlign $valign): array {
    $top = match ($valign) {
      VAlign::Top => 0,
      VAlign::Middle => intdiv(max(0, $area_rows - $box_height), 2),
      VAlign::Bottom => max(0, $area_rows - $box_height),
    };

    $left = match ($halign) {
      HAlign::Left => 0,
      HAlign::Center => intdiv(max(0, $area_columns - $box_width), 2),
      HAlign::Right => max(0, $area_columns - $box_width),
    };

    return [$top, $left];
  }

  /**
   * Splice box lines over plain backdrop lines at a position.
   *
   * Box lines that fall past the backdrop's last row are clipped - the backdrop
   * bounds the composite, so a box taller than the backdrop loses its overflow.
   *
   * @param list<string> $backdrop
   *   The backdrop lines: plain text (no ANSI), each already padded to at least
   *   the box's right edge.
   * @param list<string> $box
   *   The box lines to overlay (may carry ANSI codes).
   * @param int $box_width
   *   The box's visible width - the column span it occupies.
   * @param int $top
   *   The row offset of the box's first line.
   * @param int $left
   *   The column offset of the box's left edge.
   * @param (callable(string): string)|null $style_backdrop
   *   Styles an exposed plain-text backdrop segment (e.g. dims it); NULL
   *   leaves the backdrop as-is.
   *
   * @return list<string>
   *   The composited lines.
   */
  public static function composite(array $backdrop, array $box, int $box_width, int $top, int $left, ?callable $style_backdrop = NULL): array {
    $style_backdrop ??= static fn(string $segment): string => $segment;
    $out = [];

    foreach ($backdrop as $row => $line) {
      $box_index = $row - $top;

      if ($box_index < 0 || $box_index >= count($box)) {
        $out[] = self::style($line, $style_backdrop);

        continue;
      }

      $prefix = Strings::substr($line, 0, $left);
      $suffix = Strings::substr($line, $left + $box_width);

      $out[] = self::style($prefix, $style_backdrop) . $box[$box_index] . self::style($suffix, $style_backdrop);
    }

    return $out;
  }

  /**
   * Style a backdrop segment, leaving an empty segment untouched.
   *
   * @param string $segment
   *   The plain-text backdrop segment.
   * @param callable(string):string $style_backdrop
   *   The styling callback.
   *
   * @return string
   *   The styled segment, or the empty string when there is nothing to style.
   */
  protected static function style(string $segment, callable $style_backdrop): string {
    return $segment === '' ? '' : $style_backdrop($segment);
  }

}
