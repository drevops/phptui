<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Capability;

/**
 * A surface whose contents may overflow it and be scrolled through.
 *
 * A region can be one, with the blocks it holds as the contents, and so can
 * an arrangement whose lines move together, with every line at once as the
 * contents. No region sees its siblings, so scrolling the whole belongs to
 * the arrangement, as sizing does.
 *
 * Either way the surface only holds the offset and clamps it. The extent the
 * offset indexes into is measured where things are drawn, and the driver
 * moves it, so one rule keeps the cursor in sight whatever is moved.
 *
 * @package DrevOps\Tui\Screen\Capability
 */
interface ScrollCapableInterface {

  /**
   * Allow this surface's contents to overflow it.
   *
   * @return static
   *   The surface.
   */
  public function scrolls(): static;

  /**
   * Whether this surface's contents may overflow it.
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
   *
   * @throws \LogicException
   *   When the surface does not scroll.
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
   *   The offset, clamped so the contents cannot scroll past their own end.
   */
  public function offset(int $content, int $visible): int;

}
