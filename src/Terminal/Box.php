<?php

declare(strict_types=1);

namespace DrevOps\Tui\Terminal;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Utils\Strings;

/**
 * Pure box-drawing geometry: character sets and line/rule fitting.
 *
 * Theme-agnostic, like {@see Ansi} and {@see Table} - it knows nothing about
 * colours. A theme picks a border style and colours the result; this returns
 * the raw characters and does the width maths.
 *
 * @package DrevOps\Tui\Terminal
 */
final class Box {

  /**
   * The corner, junction, horizontal and vertical glyphs for a border style.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The border style; None shares the single-line set (a caller normally
   *   skips boxing entirely for a borderless frame).
   * @param bool $unicode
   *   Whether Unicode glyphs are used; FALSE returns the ASCII fallback set.
   *
   * @return array<string,string>
   *   Keyed by position: tl, tr, bl, br (corners), ml, mr (row junctions), h
   *   (horizontal), v (vertical), tt, bt (top and bottom column tees) and cx
   *   (the interior cross) - the last three joining a table's column
   *   separators to its rules.
   */
  public static function chars(Border $style, bool $unicode): array {
    $keys = ['tl', 'tr', 'bl', 'br', 'ml', 'mr', 'h', 'v', 'tt', 'bt', 'cx'];

    if (!$unicode) {
      return array_combine($keys, Strings::split($style === Border::Double ? '++++++=|+++' : '++++++-|+++'));
    }

    $set = match ($style) {
      Border::Rounded => '╭╮╰╯├┤─│┬┴┼',
      Border::Double => '╔╗╚╝╠╣═║╦╩╬',
      Border::None, Border::Line => '┌┐└┘├┤─│┬┴┼',
    };

    return array_combine($keys, Strings::split($set));
  }

  /**
   * A horizontal rule spanning the outer width: left + fill + right.
   *
   * @param string $left
   *   The left corner or junction glyph.
   * @param string $right
   *   The right corner or junction glyph.
   * @param string $fill
   *   The horizontal fill glyph.
   * @param int $outer_width
   *   The total width the rule spans.
   *
   * @return string
   *   The rule (unstyled).
   */
  public static function rule(string $left, string $right, string $fill, int $outer_width): string {
    return $left . str_repeat($fill, max(0, $outer_width - 2)) . $right;
  }

  /**
   * Fit content to an exact visible width: clip if too wide, pad if too short.
   *
   * ANSI-aware throughout - the visible width ignores escape codes, and a clip
   * keeps the styling of what it kept, so a row cut at an edge reads exactly
   * as the same row one line in.
   *
   * @param string $content
   *   The content (may carry ANSI codes).
   * @param int $inner_width
   *   The target visible width.
   *
   * @return string
   *   The content padded (or clipped) to exactly the inner width.
   */
  public static function fit(string $content, int $inner_width): string {
    $width = Ansi::width($content);

    if ($width > $inner_width) {
      $content = Ansi::slice($content, $inner_width);
      $width = Ansi::width($content);
    }

    return $content . str_repeat(' ', max(0, $inner_width - $width));
  }

}
