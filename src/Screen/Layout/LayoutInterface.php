<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Region;

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
 * a panel that know nothing of each other.
 *
 * {@see AbstractLayout} carries the sizing arithmetic every layout inherits.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
interface LayoutInterface {

  /**
   * The direction this layout's regions run.
   *
   * @return \DrevOps\Tui\Screen\Axis
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
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  public function in(string $name): Region;

  /**
   * Work out how much of the axis each region gets.
   *
   * @param int $available
   *   The cells to divide.
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name.
   */
  public function arrange(int $available): array;

}
