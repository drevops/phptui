<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A theme that reads the terminal's background.
 *
 * Declaring this is what grants an element the question it needs to pick a
 * palette: a colour legible on a dark terminal is not legible on a light one.
 *
 * @package DrevOps\Tui\Theme
 */
interface SupportsScheme {

  /**
   * Whether the terminal's background is dark.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isDark(): bool;

}
