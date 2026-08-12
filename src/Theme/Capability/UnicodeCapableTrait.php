<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Capability;

/**
 * Glyph behaviour: the one place a glyph is weighed against its stand-in.
 *
 * A theme that composes this states the flag and inherits the choice, so no
 * element has to remember which display mode it is drawing for.
 *
 * @package DrevOps\Tui\Theme\Capability
 */
trait UnicodeCapableTrait {

  /**
   * Whether the terminal draws glyphs outside ASCII.
   */
  protected bool $unicode = TRUE;

  /**
   * {@inheritdoc}
   */
  public function isUnicode(): bool {
    return $this->unicode;
  }

  /**
   * Pick between a glyph and the stand-in that reads where it cannot be drawn.
   *
   * Both forms are stated together so neither display mode can be set and the
   * other silently left broken.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   *
   * @return string
   *   Whichever the terminal draws.
   */
  protected function glyph(string $glyph, string $ascii): string {
    return $this->unicode ? $glyph : $ascii;
  }

}
