<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Screen\Axis;

/**
 * Three regions stacked, with the middle one scrolling.
 *
 * The header and the footer are one row each whatever the terminal height,
 * which no share can say, and the content takes whatever is left.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class DefaultLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region(self::HEADER)->fixed(1);
    $this->region(self::CONTENT)->scrolls();
    $this->region(self::FOOTER)->fixed(1);
  }

}
