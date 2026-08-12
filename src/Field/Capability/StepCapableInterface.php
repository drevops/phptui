<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field whose value advances through an ordered (or cyclic) domain.
 *
 * The domain is the field's own: bounded integers step by the declared step,
 * a date cursor steps by days, a fixed value set cycles.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface StepCapableInterface {

  /**
   * Advance the value by the given number of positions in its domain.
   *
   * @param int $delta
   *   The signed number of positions to move.
   */
  public function stepBy(int $delta): void;

}
