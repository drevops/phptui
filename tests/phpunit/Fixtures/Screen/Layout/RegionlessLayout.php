<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Screen\Layout;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: an arrangement with nowhere to put anything.
 *
 * A layout declaring no region at all, so a test can prove a panel arranged by
 * one is refused where it is named rather than rendering nothing.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Screen\Layout
 */
final class RegionlessLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);
  }

}
