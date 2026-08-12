<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Override;

use DrevOps\PhpTui\Theme\Sgr;

/**
 * The legend's elements, as a consumer patches them.
 *
 * The block's prefix is implied by the group, so `separator()` here is the
 * legend's separator and nothing else's.
 *
 * @package DrevOps\PhpTui\Theme\Override
 */
final class LegendOverrides {

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
   * Draw the mark between two entries with this glyph.
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
    $this->overrides->setGlyph(ThemeElement::LegendSeparator, $glyph, $ascii);

    return $this;
  }

  /**
   * Paint a key in these colours.
   *
   * @param \DrevOps\PhpTui\Theme\Sgr ...$parts
   *   The palette parts, in order (e.g. Sgr::Bold, Sgr::Cyan).
   *
   * @return $this
   *   The group.
   */
  public function key(Sgr ...$parts): self {
    $this->overrides->setStyle(ThemeElement::LegendKey, Sgr::of(...$parts));

    return $this;
  }

}
