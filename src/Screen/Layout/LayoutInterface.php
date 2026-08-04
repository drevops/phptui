<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Furniture;
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
 * a panel that know nothing of each other. What it does say is which of its
 * regions each piece of the standard furniture belongs in: a role rather than a
 * block, so the answer is still arrangement.
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
   * The region a piece of the standard furniture goes in.
   *
   * @param \DrevOps\Tui\Screen\Furniture $piece
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
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name.
   */
  public function arrange(int $available): array;

  /**
   * How this layout deals the blocks a region flows into visual rows.
   *
   * @return list<int>
   *   How many blocks share each visual row, top to bottom; empty runs them
   *   one under another, which is what every arrangement but a grid does.
   */
  public function deal(): array;

  /**
   * Work out how much of a region each block sharing one visual row gets.
   *
   * @param int $available
   *   The cells across the region.
   * @param int $count
   *   How many blocks share the row.
   *
   * @return int
   *   The cells each of them gets.
   */
  public function share(int $available, int $count): int;

}
