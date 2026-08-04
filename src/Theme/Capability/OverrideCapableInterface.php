<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Capability;

use DrevOps\Tui\Theme\Override\Overrides;

/**
 * A theme that takes the elements a consumer states differently.
 *
 * Declaring this is what lets a patch reach a theme at all - a handful of
 * glyphs, a separator, one style, stated without writing a class. A theme that
 * declares nothing keeps every answer of its own and the patch goes nowhere,
 * which is why a patch can never be what decides whether a form draws.
 *
 * @package DrevOps\Tui\Theme\Capability
 */
interface OverrideCapableInterface {

  /**
   * Take the elements a consumer states differently.
   *
   * @param \DrevOps\Tui\Theme\Override\Overrides $overrides
   *   The patch; anything it does not name keeps the theme's own answer.
   *
   * @return static
   *   The theme.
   */
  public function overrides(Overrides $overrides): static;

}
