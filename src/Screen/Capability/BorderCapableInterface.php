<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Capability;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\BorderSide;

/**
 * Declares that something draws edges around the space it occupies.
 *
 * Implemented by everything that occupies a rectangle - the screen, a region
 * of a layout, and any block - so one call means the same thing at every
 * level.
 *
 * The declaration carries no geometry. What a thing occupies is known where it
 * is drawn rather than where it is declared, so the renderer sizes the box and
 * the theme draws it in the style named here, or in its own when none is.
 *
 * @package DrevOps\Tui\Screen\Capability
 */
interface BorderCapableInterface {

  /**
   * Draw edges around this.
   *
   * @code
   * $block->border(BorderSide::TOP | BorderSide::BOTTOM);
   * $region->border(BorderSide::ALL, Border::Rounded, 'Today');
   * @endcode
   *
   * @param int $sides
   *   The edges to draw, combined from {@see BorderSide}.
   * @param \DrevOps\Tui\Theme\Border|null $style
   *   The style to draw them in, or NULL for the theme's own.
   * @param string $title
   *   The text written into the top edge; empty writes none, and so does a
   *   border that draws no top edge.
   *
   * @return static
   *   The thing declaring it.
   */
  public function border(int $sides = BorderSide::ALL, ?Border $style = NULL, string $title = ''): static;

  /**
   * Whether edges are drawn around this.
   *
   * @return bool
   *   TRUE when they are. A style of {@see Border::None} means no border, so
   *   it returns FALSE.
   */
  public function isBordered(): bool;

  /**
   * The style the edges are drawn in.
   *
   * @return \DrevOps\Tui\Theme\Border|null
   *   The style, or NULL when the theme's own is used.
   */
  public function borderStyle(): ?Border;

  /**
   * The text written into the top edge.
   *
   * @return string
   *   The title, or an empty string when none was stated.
   */
  public function borderTitle(): string;

  /**
   * The edges that are drawn.
   *
   * @return int
   *   The combination, from {@see BorderSide}.
   */
  public function borderSides(): int;

}
