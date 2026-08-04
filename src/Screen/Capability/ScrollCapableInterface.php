<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Capability;

/**
 * A surface whose contents may outrun it, and be moved through.
 *
 * Two things can be one: a region, whose contents are the blocks it holds, and
 * an arrangement whose lines move together, whose contents are every line at
 * once. The second is not a region's to do, because no region sees its
 * siblings - which is the same reason sizing belongs to the arrangement.
 *
 * Either way the surface only holds the offset and clamps it. What it is
 * showing of what is measured where things are drawn, and moving it is the
 * driver's, so the rule that keeps the cursor in sight lives in one place
 * whatever it is moving.
 *
 * @package DrevOps\Tui\Screen\Capability
 */
interface ScrollCapableInterface {

  /**
   * Let this surface's contents outrun it.
   *
   * @return static
   *   The surface.
   */
  public function scrolls(): static;

  /**
   * Whether this surface's contents may outrun it.
   *
   * @return bool
   *   TRUE when they may.
   */
  public function isScrolling(): bool;

  /**
   * Move the window onto this surface's contents.
   *
   * @param int $row
   *   The first row of the contents to show.
   *
   * @return static
   *   The surface.
   */
  public function scrollTo(int $row): static;

  /**
   * The first row of this surface's contents that is visible.
   *
   * @param int $content
   *   The rows its contents come to.
   * @param int $visible
   *   The rows it was given.
   *
   * @return int
   *   The offset, never far enough to scroll the contents off their own end.
   */
  public function offset(int $content, int $visible): int;

}
