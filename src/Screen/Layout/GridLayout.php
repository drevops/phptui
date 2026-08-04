<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Screen\Axis;

/**
 * Windows dealt into visual rows, each row sharing the width between them.
 *
 * A grid is one region rather than one region per window, because the two
 * questions a window raises are answered at different levels. How tall it is
 * belongs to what it holds, and blocks taking their natural size one under
 * another and scrolling together is what a region does - so a grid whose
 * windows were regions would have its height apportioned by the layout and
 * could no longer scroll as one thing. How wide it is cannot be answered by
 * the window, because its neighbours in the visual row come off the width
 * first: that is sizing, which only the thing seeing every window can do, and
 * it is the whole of what this class adds.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class GridLayout extends AbstractLayout {

  /**
   * The columns left clear between one window of a visual row and the next.
   */
  protected const int GUTTER = 2;

  /**
   * How many windows share each visual row, top to bottom.
   *
   * @var list<int>
   */
  protected array $shape;

  /**
   * Construct the layout.
   *
   * @param int ...$rows
   *   One entry per visual row, naming how many windows share it, top to
   *   bottom; none runs them one under another.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When a visual row holds fewer than one window, which is a row nothing
   *   could ever be dealt into.
   */
  public function __construct(int ...$rows) {
    parent::__construct(Axis::Rows);

    foreach ($rows as $count) {
      if ($count < 1) {
        throw new FormException('Every visual row of a grid holds at least one window.');
      }
    }

    $this->shape = array_values($rows);
    $this->region('content')->scrolls();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function deal(): array {
    return $this->shape;
  }

  /**
   * {@inheritdoc}
   *
   * The gutters come off the width before it is divided, so the windows of a
   * row are the same width whatever the terminal, and never so narrow that one
   * of them has no column at all to draw in.
   */
  #[\Override]
  public function share(int $available, int $count): int {
    return max(1, intdiv($available - ($count - 1) * self::GUTTER, max(1, $count)));
  }

  /**
   * Assert this grid's slots cover exactly the windows dealt into it.
   *
   * @param int $windows
   *   How many windows there are to deal.
   * @param string $owner
   *   What declared the grid, as the name it goes by.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the slots do not cover the windows, which would leave one of them
   *   undrawn or a row of the grid empty.
   */
  public function assertDeals(int $windows, string $owner): void {
    if ($this->shape === [] || array_sum($this->shape) === $windows) {
      return;
    }

    throw new FormException(sprintf('The grid of "%s" declares %d slot(s) for %d window(s).', $owner, array_sum($this->shape), $windows));
  }

}
