<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Furniture;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: a layout that keeps the trail and the keys off the screen.
 *
 * It declares the conventional regions and still answers NULL for everything
 * but the form, so a test can prove a piece with nowhere to go is left undrawn
 * rather than falling back to a region that happens to exist.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Screen\Layout
 */
final class BareLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('header')->fixed(1);
    $this->region('content')->scrolls();
    $this->region('footer')->fixed(1);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function furnishes(Furniture $piece): ?string {
    return $piece === Furniture::Body ? parent::furnishes($piece) : NULL;
  }

}
