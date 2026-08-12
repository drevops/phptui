<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Capability;

use DrevOps\PhpTui\Theme\Override\Overrides;

/**
 * A theme that takes the elements a consumer states differently.
 *
 * Declaring this is what lets a patch reach a theme at all - a handful of
 * glyphs, a separator, one style, stated without writing a class. A theme that
 * declares nothing keeps every answer of its own and the patch goes nowhere,
 * which is why a patch can never be what decides whether a form draws.
 *
 * @package DrevOps\PhpTui\Theme\Capability
 */
interface OverrideCapableInterface {

  /**
   * Take the elements a consumer states differently.
   *
   * @param \DrevOps\PhpTui\Theme\Override\Overrides $overrides
   *   The patch; anything it does not name keeps the theme's own answer.
   *
   * @return static
   *   The theme.
   */
  public function overrides(Overrides $overrides): static;

}
