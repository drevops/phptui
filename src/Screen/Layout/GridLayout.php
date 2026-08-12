<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen\Layout;

use DrevOps\PhpTui\Block\Element\ChromeElementsInterface;
use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Screen\Axis;

/**
 * Windows dealt into visual rows, each row sharing the width between them.
 *
 * A window is a region, so a grid declares one for each of them: a panel
 * reaches a window by naming it, exactly as a block reaches any other region,
 * and nothing has to look past its own level to find out where anything went.
 * The regions run `above`, which holds the rows written before the first
 * window, then `window-1` upward in reading order - across the first visual
 * row, then across the second - and last `below`, which holds the rows written
 * after them. Where a row sits is which of the pair it is in rather than a
 * count of what it was written past.
 *
 * What the grid adds over a stack of regions is both of the questions a window
 * raises. How deep it is is what it holds, which is a size a region can state
 * and this apportions. How wide it is cannot be answered by the window at all,
 * because the neighbours it shares a visual row with come off the width first:
 * only the thing that sees every window can divide it.
 *
 * It scrolls as one surface for the same reason it sizes them: a grid too tall
 * for its space has to move every line together, and no window can move its
 * siblings.
 *
 * A shape is not a name, so this is the one shipped layout a form cannot pick
 * by name: two grids of different shapes are different arrangements, and a name
 * carries no shape. It is built where the shape is written.
 *
 * @package DrevOps\PhpTui\Screen\Layout
 */
final class GridLayout extends AbstractLayout {

  /**
   * The region holding the rows written before the first window.
   */
  protected const string ABOVE = 'above';

  /**
   * The name each window region carries, before its place in reading order.
   */
  protected const string WINDOW = 'window-';

  /**
   * The region holding the rows written after the last window.
   */
  protected const string BELOW = 'below';

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
   * @throws \DrevOps\PhpTui\FormException
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
    $this->scrolls();
    $this->region(self::ABOVE)->content();

    for ($number = 1; $number <= array_sum($this->shape); $number++) {
      $this->windows[] = self::WINDOW . $number;
      $this->region(self::WINDOW . $number)->content()->previews();
    }

    $this->region(self::BELOW)->content();
  }

  /**
   * {@inheritdoc}
   *
   * The rows written either side of the grid have a line to themselves, which
   * is what keeps them out of it, and each visual row of windows takes one
   * line between them.
   */
  #[\Override]
  public function lines(): array {
    $lines = [[self::ABOVE]];
    $taken = 0;

    foreach ($this->shape as $count) {
      $lines[] = array_slice($this->windows, $taken, $count);
      $taken += $count;
    }

    $lines[] = [self::BELOW];

    return $lines;
  }

  /**
   * The region holding the rows written before the first window.
   *
   * @return string
   *   The name.
   */
  public function leading(): string {
    return self::ABOVE;
  }

  /**
   * The region holding the rows written after the last window.
   *
   * @return string
   *   The name.
   */
  public function trailing(): string {
    return self::BELOW;
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
   * @param int $panels
   *   How many panels there are to deal.
   * @param string $owner
   *   What declared the grid, as the name it goes by.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the windows do not cover the panels, which would leave one of them
   *   undrawn or a row of the grid empty.
   */
  public function assertDeals(int $panels, string $owner): void {
    if (count($this->windows) === $panels) {
      return;
    }

    throw new FormException(sprintf('The grid of "%s" declares %d window(s) for %d panel(s).', $owner, count($this->windows), $panels));
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
