<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen\Layout;

use DrevOps\PhpTui\Block\Element\ChromeElementsInterface;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Capability\ScrollCapableInterface;
use DrevOps\PhpTui\Screen\Furniture;
use DrevOps\PhpTui\Screen\Region;

/**
 * An arrangement of named regions along one axis.
 *
 * Arranging is flowing plus sizing: a layout runs its regions in one direction
 * like a region runs its blocks, and it alone apportions the axis between them,
 * because only the thing that sees every region can take the fixed ones off the
 * top before the remainder is divided.
 *
 * It draws nothing and names no block. Naming one would cost it the reuse that
 * is the reason it is a class of its own - one definition serving a screen and
 * a panel that know nothing of each other. What it does say is which of its
 * regions each piece of the standard furniture belongs in: a role rather than a
 * block, so the answer is still arrangement.
 *
 * An arrangement can be a scrolling surface as well, which a region cannot do
 * for it: moving every line together is only possible for the thing that sees
 * every line, exactly as sizing them is.
 *
 * {@see AbstractLayout} carries the sizing arithmetic every layout inherits.
 *
 * @package DrevOps\PhpTui\Screen\Layout
 */
interface LayoutInterface extends ScrollCapableInterface {

  /**
   * The direction this layout's regions run.
   *
   * @return \DrevOps\PhpTui\Screen\Axis
   *   The axis.
   */
  public function axis(): Axis;

  /**
   * The names of the regions this layout declares, in declaration order.
   *
   * @return list<string>
   *   The names.
   */
  public function names(): array;

  /**
   * The region of a given name.
   *
   * @param string $name
   *   The region name.
   *
   * @return \DrevOps\PhpTui\Screen\Region
   *   The region.
   */
  public function in(string $name): Region;

  /**
   * The region a piece of the standard furniture goes in.
   *
   * @param \DrevOps\PhpTui\Screen\Furniture $piece
   *   The piece.
   *
   * @return string|null
   *   The region name, or NULL when this layout keeps no place for the piece -
   *   which is a refusal rather than an oversight: the piece is never drawn.
   */
  public function furnishes(Furniture $piece): ?string;

  /**
   * Work out how much of the axis each region gets.
   *
   * @param int $available
   *   The cells to divide.
   * @param array<string,int> $measured
   *   The cells each region's contents come to, keyed by name, for the ones
   *   that take as much of the axis as what they hold. Numbers rather than
   *   anything that could measure: what a region holds is drawn where drawing
   *   happens, and a layout that could ask would be a layout that knows there
   *   are blocks.
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name.
   */
  public function arrange(int $available, array $measured = []): array;

  /**
   * The cells this arrangement comes to when nothing has sized it.
   *
   * @param array<string,int> $measured
   *   The cells each region's contents come to, keyed by name.
   *
   * @return int
   *   The cells: what its lines come to together down the axis, and what the
   *   deepest of them comes to across it, where they share the cells instead.
   */
  public function natural(array $measured = []): int;

  /**
   * The regions drawn on each line of the axis, in declaration order.
   *
   * @return list<list<string>>
   *   One entry per line, naming the regions drawn on it. Most arrangements
   *   put one region on a line; a grid puts a visual row's windows on one.
   */
  public function lines(): array;

  /**
   * Work out how much of the cross-axis each region sharing a line gets.
   *
   * @param int $available
   *   The cells across the line.
   * @param int $count
   *   How many regions share it.
   * @param \DrevOps\PhpTui\Block\Element\ChromeElementsInterface $chrome
   *   The theme, for the gutter left between two things drawn side by side:
   *   an arrangement cannot divide a width without knowing what the air
   *   between the pieces costs, and how wide that air is is the theme's.
   *
   * @return int
   *   The cells each of them gets.
   */
  public function share(int $available, int $count, ChromeElementsInterface $chrome): int;

}
