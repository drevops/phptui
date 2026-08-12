<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Capability;

/**
 * A theme that paints, and reads the background it paints onto.
 *
 * One declaration rather than two, because the two questions are never asked
 * apart: a colour is chosen against a background, and a colour legible on a
 * dark terminal is not legible on a light one. Declaring this is what grants an
 * element a palette to draw from and the question it needs to pick between two.
 *
 * A theme that declares nothing still works - it hands back the strings it was
 * given - which is why a form renders in a terminal that supports nothing.
 *
 * @package DrevOps\PhpTui\Theme\Capability
 */
interface ColorSchemeCapableInterface {

  /**
   * Whether colour reaches the terminal.
   *
   * @return bool
   *   TRUE when an element may paint.
   */
  public function isColor(): bool;

  /**
   * Whether the terminal's background is dark.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isDark(): bool;

}
