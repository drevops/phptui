<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Capability;

/**
 * A theme that can draw glyphs outside ASCII.
 *
 * Declaring this is what lets an element choose between a glyph and its ASCII
 * stand-in, rather than picking one and hoping.
 *
 * @package DrevOps\Tui\Theme\Capability
 */
interface UnicodeCapableInterface {

  /**
   * Whether the terminal draws glyphs outside ASCII.
   *
   * @return bool
   *   TRUE when an element may reach for one.
   */
  public function hasUnicode(): bool;

}
