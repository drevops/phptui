<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Screen\Axis;

/**
 * Windows dealt into visual rows, each row sharing the width between them.
 *
 * A window is a region, so a grid declares one for each of them: a panel
 * reaches a window by naming it, exactly as a block reaches any other region,
 * and nothing has to look past its own level to find out where anything went.
 * The regions run `content`, which holds the panel's own rows above the grid,
 * and then `window-1` upward in reading order - across the first visual row,
 * then across the second.
 *
 * What the grid adds over a stack of regions is both of the questions a window
 * raises. How deep it is is what it holds, which is a size a region can state
 * and this apportions. How wide it is cannot be answered by the window at all,
 * because the neighbours it shares a visual row with come off the width first:
 * only the thing that sees every window can divide it.
 *
 * A shape is not a name, so this is the one shipped layout a form cannot pick
 * by name: two grids of different shapes are different arrangements, and a name
 * carries no shape. It is built where the shape is written.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class GridLayout extends AbstractLayout {

  /**
   * The name each window region carries, before its place in reading order.
   */
  protected const string WINDOW = 'window-';

  /**
   * How many windows share each visual row, top to bottom.
   *
   * @var list<int>
   */
  protected array $shape;

  /**
   * The regions its windows are drawn in, in reading order.
   *
   * @var list<string>
   */
  protected array $windows = [];

  /**
   * Construct the layout.
   *
   * @param int ...$rows
   *   One entry per visual row, naming how many windows share it, top to
   *   bottom.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When no visual row is declared, or one of them holds fewer than one
   *   window - a row nothing could ever be dealt into.
   */
  public function __construct(int ...$rows) {
    parent::__construct(Axis::Rows);

    if ($rows === []) {
      throw new FormException('A grid is a shape, so it is declared with the windows of at least one visual row.');
    }

    foreach ($rows as $count) {
      if ($count < 1) {
        throw new FormException('Every visual row of a grid holds at least one window.');
      }
    }

    $this->shape = array_values($rows);
    $this->region(self::CONTENT)->content()->scrolls();

    for ($number = 1; $number <= array_sum($this->shape); $number++) {
      $this->windows[] = self::WINDOW . $number;
      $this->region(self::WINDOW . $number)->content()->scrolls()->previews();
    }
  }

  /**
   * {@inheritdoc}
   *
   * The panel's own rows have the first line to themselves, which is what puts
   * them above the grid rather than in it, and each visual row of windows takes
   * one line after that.
   */
  #[\Override]
  public function lines(): array {
    $lines = [[self::CONTENT]];
    $taken = 0;

    foreach ($this->shape as $count) {
      $lines[] = array_slice($this->windows, $taken, $count);
      $taken += $count;
    }

    return $lines;
  }

  /**
   * {@inheritdoc}
   *
   * The gutters come off the width before it is divided, so the windows of a
   * row are the same width whatever the terminal, and never so narrow that one
   * of them has no column at all to draw in.
   */
  #[\Override]
  public function share(int $available, int $count, ChromeElementsInterface $chrome): int {
    return max(1, intdiv($available - ($count - 1) * $chrome->chromeGutter(), max(1, $count)));
  }

  /**
   * The regions this grid draws its windows in, in reading order.
   *
   * @return list<string>
   *   The names.
   */
  public function windows(): array {
    return $this->windows;
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
    if (count($this->windows) === $windows) {
      return;
    }

    throw new FormException(sprintf('The grid of "%s" declares %d slot(s) for %d window(s).', $owner, count($this->windows), $windows));
  }

  /**
   * {@inheritdoc}
   *
   * Two visual rows drawn against each other read as one, so a grid keeps a row
   * between them whatever the theme says about the air between blocks.
   */
  #[\Override]
  protected function separator(): int {
    return 1;
  }

}
