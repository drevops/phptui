<?php

declare(strict_types=1);

namespace Playground\Layouts;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;

/**
 * A board sized to its rows above a 5-row log that follows its tail.
 */
final class BenchLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('content')->content();
    $this->region('log')->fixed(5)->follows();
  }

}
