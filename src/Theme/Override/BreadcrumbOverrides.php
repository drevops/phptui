<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Override;

/**
 * The breadcrumb's elements, as a consumer patches them.
 *
 * The block's prefix is implied by the group, so `separator()` here is the
 * breadcrumb's separator and nothing else's.
 *
 * @package DrevOps\PhpTui\Theme\Override
 */
final class BreadcrumbOverrides {

  /**
   * Construct a group over the patch it writes into.
   *
   * @param \DrevOps\PhpTui\Theme\Override\Overrides $overrides
   *   The patch being collected.
   */
  public function __construct(
    protected Overrides $overrides,
  ) {
  }

  /**
   * Draw the mark between two segments with this glyph.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return $this
   *   The group.
   */
  public function separator(string $glyph, string $ascii): self {
    $this->overrides->setGlyph(ThemeElement::BreadcrumbSeparator, $glyph, $ascii);

    return $this;
  }

}
