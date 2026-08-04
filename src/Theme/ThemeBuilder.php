<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Theme\Override\BreadcrumbOverrides;
use DrevOps\Tui\Theme\Override\FieldOverrides;
use DrevOps\Tui\Theme\Override\LegendOverrides;
use DrevOps\Tui\Theme\Override\Overrides;

/**
 * Patches a theme's elements without subclassing it.
 *
 * Subclassing is the full answer and overkill when all you want is a different
 * glyph. The overrides are grouped by the block that declares the elements, so
 * the prefix is implied and `separator()` means one thing under `breadcrumb()`
 * and another under `legend()`:
 *
 * @code
 * $overrides = (new ThemeBuilder())
 *   ->breadcrumb(fn(BreadcrumbOverrides $b) => $b
 *     ->separator('›', '>'))
 *   ->legend(fn(LegendOverrides $l) => $l
 *     ->separator('·', '|')
 *     ->key(Sgr::Bold))
 *   ->field(fn(FieldOverrides $f) => $f
 *     ->selector('❯', '>')
 *     ->helpMarker('ⁱ', '[?]')
 *     ->valueSeparator(', ')
 *     ->entryMarker('◼', '[x]')
 *     ->caret('█', '|'))
 *   ->overrides();
 * @endcode
 *
 * Reach for a subclass when you are changing a palette; reach for this when you
 * are changing a handful of glyphs.
 *
 * @package DrevOps\Tui\Theme
 */
final class ThemeBuilder {

  /**
   * The patch every group writes into.
   */
  protected Overrides $overrides;

  /**
   * Construct a builder.
   */
  public function __construct() {
    $this->overrides = new Overrides();
  }

  /**
   * Patch the breadcrumb's elements.
   *
   * @param \Closure $group
   *   Given the breadcrumb's group, states what it draws differently.
   *
   * @return $this
   *   The builder.
   */
  public function breadcrumb(\Closure $group): self {
    $group(new BreadcrumbOverrides($this->overrides));

    return $this;
  }

  /**
   * Patch the legend's elements.
   *
   * @param \Closure $group
   *   Given the legend's group, states what it draws differently.
   *
   * @return $this
   *   The builder.
   */
  public function legend(\Closure $group): self {
    $group(new LegendOverrides($this->overrides));

    return $this;
  }

  /**
   * Patch the field's elements.
   *
   * @param \Closure $group
   *   Given the field's group, states what it draws differently.
   *
   * @return $this
   *   The builder.
   */
  public function field(\Closure $group): self {
    $group(new FieldOverrides($this->overrides));

    return $this;
  }

  /**
   * The patch collected so far.
   *
   * @return \DrevOps\Tui\Theme\Override\Overrides
   *   The overrides, holding only the elements that were stated.
   */
  public function overrides(): Overrides {
    return $this->overrides;
  }

}
