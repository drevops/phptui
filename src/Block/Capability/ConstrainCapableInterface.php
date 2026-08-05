<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Block\DateBounds;
use DrevOps\Tui\Block\NumberBounds;
use DrevOps\Tui\Block\SelectionBounds;

/**
 * A block that says what it will accept, before you act.
 *
 * A stated bound and a refusal answer different questions and share one line on
 * screen, so they never appear together: this is the one that is true before
 * anything has been offered.
 *
 * A bound comes in two halves. The phrase is what a person reads, and the
 * bounds object is what a value is measured against - the same object every
 * surface measures with, so the panel, the headless path and the schema cannot
 * disagree about what is in range.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface ConstrainCapableInterface {

  /**
   * State what this block will accept.
   *
   * @param string $constraint
   *   The constraint, as an unframed phrase its caller wraps.
   *
   * @return static
   *   The block.
   */
  public function constrain(string $constraint): static;

  /**
   * What this block will accept.
   *
   * @return string|null
   *   The constraint, or NULL when it states none.
   */
  public function constraint(): ?string;

  /**
   * Bound how large a number may be.
   *
   * @param \DrevOps\Tui\Block\NumberBounds $bounds
   *   The minimum, maximum and keyboard step.
   *
   * @return static
   *   The block.
   */
  public function bounds(NumberBounds $bounds): static;

  /**
   * How large a number may be.
   *
   * @return \DrevOps\Tui\Block\NumberBounds|null
   *   The bounds, or NULL when the magnitude is unbounded.
   */
  public function numberBounds(): ?NumberBounds;

  /**
   * Bound how early or late a date may be.
   *
   * @param \DrevOps\Tui\Block\DateBounds $bounds
   *   The range, and the day its calendar week begins on.
   *
   * @return static
   *   The block.
   */
  public function dates(DateBounds $bounds): static;

  /**
   * How early or late a date may be.
   *
   * @return \DrevOps\Tui\Block\DateBounds|null
   *   The bounds, or NULL when the range is unbounded.
   */
  public function dateBounds(): ?DateBounds;

  /**
   * Bound how many values a list may hold.
   *
   * @param \DrevOps\Tui\Block\SelectionBounds $bounds
   *   The minimum and maximum count.
   *
   * @return static
   *   The block.
   */
  public function selections(SelectionBounds $bounds): static;

  /**
   * How many values a list may hold.
   *
   * @return \DrevOps\Tui\Block\SelectionBounds|null
   *   The bounds, or NULL when the count is unbounded.
   */
  public function selectionBounds(): ?SelectionBounds;

  /**
   * The first bound a value falls outside, as a fragment.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The fragment (e.g. "between 1 and 10", "at least 2 items"), or NULL when
   *   the value is in range or nothing bounds it.
   */
  public function boundsViolation(mixed $value): ?string;

}
