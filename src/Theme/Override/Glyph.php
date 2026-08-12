<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Override;

/**
 * A glyph and the ASCII stand-in that reads where it cannot be drawn.
 *
 * The pair travels together so a consumer cannot set one display mode and
 * silently leave the other broken.
 *
 * @package DrevOps\PhpTui\Theme\Override
 */
final readonly class Glyph {

  /**
   * Construct a glyph pair.
   *
   * @param string $glyph
   *   The glyph.
   * @param string $ascii
   *   Its ASCII stand-in.
   */
  public function __construct(
    public string $glyph,
    public string $ascii,
  ) {
  }

}
