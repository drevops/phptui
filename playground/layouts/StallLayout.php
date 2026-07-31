<?php

declare(strict_types=1);

namespace Playground\Layouts;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * The produce beside the delivery notes, in two columns of unequal width.
 *
 * AbstractLayout carries every line of the sizing arithmetic, so a subclass
 * only says which way its regions run and what they are called. The names are
 * the whole point: a block goes in by name, so nothing here depends on the
 * order anything was declared in.
 *
 * A share is of whatever is left over rather than of anything in particular,
 * so three and two mean the same as thirty and twenty - the produce column
 * simply takes half again what the delivery column does, at every terminal
 * width.
 */
final class StallLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    // Scrolling is declared per region rather than per layout, which is what
    // lets a long produce list outrun its column while its neighbour stays put.
    $this->region('produce')->flex(3)->scrolls();
    $this->region('delivery')->flex(2);
  }

}
