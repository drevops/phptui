<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: an arrangement with nowhere to put anything.
 *
 * A layout declaring no region at all, so a test can prove a panel arranged by
 * one is refused where it is named rather than rendering nothing.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Screen\Layout
 */
final class RegionlessLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);
  }

}
