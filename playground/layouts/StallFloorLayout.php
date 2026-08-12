<?php

declare(strict_types=1);

namespace Playground\Layouts;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;
use DrevOps\PhpTui\Theme\BorderSide;

/**
 * The stalls beside a standing noticeboard, in two columns of unequal width.
 *
 * A share is of whatever is left over, so three and two mean the stalls column
 * takes half again what the noticeboard does at every terminal width.
 *
 * The noticeboard declares its own box, which is a design choice rather than a
 * debugging aid: it holds standing text rather than rows to walk, and the edge
 * says so without a heading. The box spends a row top and bottom and a column
 * each side of the cells the layout granted.
 */
final class StallFloorLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    $this->region('stalls')->flex(3)->scrolls();
    $this->region('noticeboard')->flex(2)->border(BorderSide::ALL, NULL, 'Today at the market');
  }

}
