<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Screen\Capability\BorderCapableInterface;
use DrevOps\PhpTui\Screen\Capability\BorderCapableTrait;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * Behaviour every block shares: drawing through a theme's elements.
 *
 * A block draws with the elements it declares, and {@see elements()} narrows
 * the theme to them: a theme that does not implement them throws
 * \InvalidArgumentException rather than drawing a blank line.
 *
 * Every block may also declare edges. The declaration carries no geometry -
 * what a block occupies is known where it is drawn - so the renderer sizes
 * the box.
 *
 * @package DrevOps\PhpTui\Block
 */
abstract class AbstractBlock implements BlockInterface, BorderCapableInterface {

  use BorderCapableTrait;

  /**
   * The theme, narrowed to the elements this block draws with.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param class-string<T> $elements
   *   The elements interface this block declares.
   * @param string $subject
   *   The phrase the exception message uses for what could not be drawn.
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
