<?php

declare(strict_types=1);

namespace Playground\Layouts;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Furniture;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;

/**
 * A screen with a two-row masthead, a scrolling body and a status bar.
 *
 * The masthead and the status bar run across, so each holds two blocks on one
 * line rather than nesting an arrangement to put them side by side. The body
 * takes the rows the two fixed lines leave.
 *
 * The regions are named for what they are rather than for the conventional
 * header/content/footer, so furnishes() states where each piece of the
 * standard furniture goes. A layout that kept the conventional names would
 * inherit that answer and write none of this.
 */
final class MarketHallLayout extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('masthead')->fixed(2)->flow(Axis::Columns);
    $this->region('aisles')->scrolls();
    $this->region('statusbar')->fixed(1)->flow(Axis::Columns);
  }

  /**
   * {@inheritdoc}
   *
   * Every piece is drawn, so the return narrows to a name.
   */
  #[\Override]
  public function furnishes(Furniture $piece): string {
    return match ($piece) {
      Furniture::Trail => 'masthead',
      Furniture::Body => 'aisles',
      Furniture::Keys => 'statusbar',
    };
  }

}
