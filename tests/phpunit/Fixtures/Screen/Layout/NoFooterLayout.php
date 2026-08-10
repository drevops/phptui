<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: a trail and the form, with nothing pinned under it.
 *
 * The form runs to the foot of the frame, so what it ends on is what the
 * frame's own edge meets - which is the arrangement a test needs to ask what
 * happens where a block's line and the box's line are the same line.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Screen\Layout
 */
final class NoFooterLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('header')->fixed(1);
    $this->region('content')->scrolls();
  }

}
