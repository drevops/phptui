<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * What every block has in common: it draws through a theme, or it cannot draw.
 *
 * A block fills the space it is given, and the region it sits in knows nothing
 * else about it. Order and spacing within its own output belong to the block;
 * colour and glyph belong to the theme. So the one thing every kind shares is
 * the elements it reaches for - and a theme that declares none cannot draw it,
 * which is a type error rather than a blank line.
 *
 * @package DrevOps\Tui\Block
 */
abstract class AbstractBlock implements BlockInterface {

  /**
   * The theme, narrowed to the elements this block draws with.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param class-string<T> $elements
   *   The elements interface this block declares.
   * @param string $subject
   *   What could not be drawn, as the phrase the failure names it by.
   *
   * @return T
   *   The theme, able to draw this block.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   *
   * @template T of object
   */
  protected function elements(ThemeInterface $theme, string $elements, string $subject): object {
    if (!$theme instanceof $elements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw %s: it does not implement %s.', $theme::class, $subject, $elements));
    }

    return $theme;
  }

}
