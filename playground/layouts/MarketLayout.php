<?php

declare(strict_types=1);

namespace Playground\Layouts;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * The default arrangement with a taller trail: two rows of it instead of one.
 *
 * A layout names its regions and stops there - it never mentions a breadcrumb,
 * a panel or anything else that might be drawn in it, because a layout
 * carrying content opinions is a layout exactly one form can use. What lands
 * in "header", "content" and "footer" is decided by whatever assembles the
 * screen, which is why keeping those three names is what keeps the trail, the
 * form and the key hints.
 */
final class MarketLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    // A fixed size is a count of cells, and rows are why it exists: the header
    // is two rows however tall the terminal, which no proportion can say. The
    // second is room for whatever else belongs up there, and air under the
    // trail when nothing does.
    $this->region('header')->fixed(2);
    // Declaring neither size is a share of one, so the form takes whatever the
    // two fixed rows leave, and scrolls once it has more rows than that.
    $this->region('content')->scrolls();
    $this->region('footer')->fixed(1);
  }

}
