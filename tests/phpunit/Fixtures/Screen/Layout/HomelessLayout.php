<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Furniture;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: a layout with regions that keeps no place for the form.
 *
 * The one case the assembler refuses outright, so a test can prove a form with
 * nowhere to be drawn is named where the layout is rather than mid-session.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Screen\Layout
 */
final class HomelessLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('content')->scrolls();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function furnishes(Furniture $piece): ?string {
    return $piece === Furniture::Body ? NULL : parent::furnishes($piece);
  }

}
