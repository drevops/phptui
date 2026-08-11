<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * One edge of a border.
 *
 * A border states which of these it draws, so a rule above and below something
 * is the same declaration as a box around it with two sides left off.
 *
 * Which sides are drawn is also which cells are spent: a top and a bottom cost
 * a row each and no columns, a left and a right a column each and no rows.
 *
 * @package DrevOps\Tui\Theme
 */
enum Side: string {

  case Top = 'top';

  case Right = 'right';

  case Bottom = 'bottom';

  case Left = 'left';

  /**
   * Every side, which is what a border draws when it names none.
   *
   * @return list<self>
   *   The sides.
   */
  public static function all(): array {
    return self::cases();
  }

}
