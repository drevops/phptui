<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A theme that paints.
 *
 * Declaring this is what grants an element a palette to draw from. A theme that
 * declares nothing still works - it hands back the strings it was given - which
 * is why a form renders in a terminal that supports nothing.
 *
 * @package DrevOps\Tui\Theme
 */
interface SupportsColor {

  /**
   * Whether colour reaches the terminal.
   *
   * @return bool
   *   TRUE when an element may paint.
   */
  public function hasColor(): bool;

}
