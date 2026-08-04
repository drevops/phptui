<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Capability;

use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\VAlign;

/**
 * A theme that says how much of the terminal it takes, and where.
 *
 * One declaration rather than several, because the answers are never wanted
 * apart: what fills the terminal has to say where it anchors when it does not
 * fill it, how small a terminal it can still be read in, how large it is
 * willing to grow, and what shows between what it holds. A driver that has
 * these can place a frame; one that has none draws where the cursor already is,
 * which is what a frame in a plain terminal does.
 *
 * @package DrevOps\Tui\Theme\Capability
 */
interface OccupyCapableInterface {

  /**
   * Whether the frame takes the whole terminal rather than its own content.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function isFullscreen(): bool;

  /**
   * Where a frame narrower than the terminal sits across it.
   *
   * @return \DrevOps\Tui\Theme\HAlign
   *   The anchor.
   */
  public function halign(): HAlign;

  /**
   * Where a frame shorter than the terminal sits down it.
   *
   * @return \DrevOps\Tui\Theme\VAlign
   *   The anchor.
   */
  public function valign(): VAlign;

  /**
   * The narrowest terminal the frame can still be read in.
   *
   * @return int
   *   The columns, or 0 to measure the frame's own content instead.
   */
  public function minWidth(): int;

  /**
   * The shortest terminal the frame can still be read in.
   *
   * @return int
   *   The rows.
   */
  public function minHeight(): int;

  /**
   * The widest the frame is willing to grow.
   *
   * @return int
   *   The columns, or 0 for as wide as the terminal is.
   */
  public function maxWidth(): int;

  /**
   * The tallest the frame is willing to grow.
   *
   * @return int
   *   The rows, or 0 for as tall as the terminal is.
   */
  public function maxHeight(): int;

  /**
   * What shows between the rows a region holds.
   *
   * @return \DrevOps\Tui\Theme\Spacing
   *   The spacing.
   */
  public function spacing(): Spacing;

  /**
   * The wash the whole terminal is filled with, behind everything drawn.
   *
   * @return string|null
   *   The background SGR parameters, or NULL to keep the terminal's own.
   */
  public function background(): ?string;

}
