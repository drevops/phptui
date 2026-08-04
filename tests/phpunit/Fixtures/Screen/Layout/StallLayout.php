<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Furniture;
use DrevOps\Tui\Screen\Layout\AbstractLayout;

/**
 * Test fixture: a layout that calls its regions something else.
 *
 * It says where each piece of furniture goes rather than leaving the
 * conventional names to answer, so a test can prove the assembler reads the
 * layout instead of guessing at the names it happens to have used.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Screen\Layout
 */
final class StallLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    $this->region('aside')->fixed(24);
    $this->region('main')->flex(1)->scrolls();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function furnishes(Furniture $piece): string {
    return $piece === Furniture::Body ? 'main' : 'aside';
  }

}
