<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Anything drawn in a region.
 *
 * A block fills the space it is given, and the region knows nothing else
 * about it. Order and spacing within its own output belong to the block;
 * colour and glyph belong to the theme, so render() takes the elements from
 * the theme rather than choosing either itself.
 *
 * @package DrevOps\Tui\Block
 */
interface BlockInterface {

  /**
   * Draw this block.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the elements it composes.
   *
   * @return string
   *   The rendered block; newlines separate rows.
   */
  public function render(ThemeInterface $theme): string;

}
