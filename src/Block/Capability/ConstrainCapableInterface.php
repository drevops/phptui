<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * A block that says what it will accept, before you act.
 *
 * A stated bound and a refusal answer different questions and share one line on
 * screen, so they never appear together: this is the one that is true before
 * anything has been offered.
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

}
