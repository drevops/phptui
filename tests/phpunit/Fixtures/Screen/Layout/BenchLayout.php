<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Screen\Layout;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: a content-sized board above a 3-row log that follows its end.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Screen\Layout
 */
final class BenchLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('board')->content();
    $this->region('log')->fixed(3)->follows();
  }

}
