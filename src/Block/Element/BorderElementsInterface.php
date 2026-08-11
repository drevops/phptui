<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

use DrevOps\Tui\Theme\Border;

/**
 * The glyphs a border is drawn with.
 *
 * The theme answers with characters and nothing else - no widths, no rows, no
 * knowledge of what is being boxed. Whatever draws the border works out the
 * rectangle and asks for the pieces it needs.
 *
 * Each corner, junction and run is its own method. A theme restyles one edge
 * without restating the rest, and a reader sees which character is which
 * without counting positions in a packed string.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface BorderElementsInterface {

  /**
   * The corner where the top edge meets the left.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderTopLeft(Border $style): string;

  /**
   * The corner where the top edge meets the right.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderTopRight(Border $style): string;

  /**
   * The corner where the bottom edge meets the left.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderBottomLeft(Border $style): string;

  /**
   * The corner where the bottom edge meets the right.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderBottomRight(Border $style): string;

  /**
   * The junction where a rule meets the left edge.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderLeftJunction(Border $style): string;

  /**
   * The junction where a rule meets the right edge.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderRightJunction(Border $style): string;

  /**
   * The run a horizontal edge is filled with.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderHorizontal(Border $style): string;

  /**
   * The run a vertical edge is filled with.
   *
   * @param \DrevOps\Tui\Theme\Border $style
   *   The style.
   *
   * @return string
   *   The glyph.
   */
  public function borderVertical(Border $style): string;

  /**
   * Style a drawn piece of a border.
   *
   * @param string $text
   *   The piece.
   *
   * @return string
   *   The styled piece.
   */
  public function borderPaint(string $text): string;

}
