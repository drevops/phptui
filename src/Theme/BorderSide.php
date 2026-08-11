<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The edges a border draws, combined with a bitwise or.
 *
 * @code
 * BorderSide::Top | BorderSide::Bottom
 * @endcode
 *
 * Constants rather than an enum because these combine: PHP has no operator
 * overloading, so `BorderSide::Left|BorderSide::Right` on enum cases raises a
 * TypeError. A side is a flag rather than one of a fixed set of values.
 *
 * Which sides are drawn is also which cells are spent: a top and a bottom cost
 * a row each and no columns, a left and a right a column each and no rows.
 *
 * @package DrevOps\Tui\Theme
 */
final class BorderSide {

  public const int Top = 1;

  public const int Right = 2;

  public const int Bottom = 4;

  public const int Left = 8;

  /**
   * Every edge, which is what a border draws when it names none.
   */
  public const int All = self::Top | self::Right | self::Bottom | self::Left;

  /**
   * No edge at all.
   */
  public const int None = 0;

  /**
   * Whether a combination draws a given side.
   *
   * @param int $sides
   *   The combination.
   * @param int $side
   *   The side to test.
   *
   * @return bool
   *   TRUE when it is drawn.
   */
  public static function draws(int $sides, int $side): bool {
    return ($sides & $side) === $side;
  }

}
