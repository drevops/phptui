<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Screen\Axis;

/**
 * One scrolling region, which is what a panel is most of the time.
 *
 * A single-region layout needs no special axis - it is rows that happens to
 * declare one - so it ships as a class a panel reaches for rather than as a
 * name a form picks, which would suggest a choice where there is none.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class PanelLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region(self::CONTENT)->scrolls();
  }

}
