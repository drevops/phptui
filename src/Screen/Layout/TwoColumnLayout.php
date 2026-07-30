<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Screen\Axis;

/**
 * Two regions side by side, sharing the width evenly.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class TwoColumnLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    $this->region('left');
    $this->region('right');
  }

}
