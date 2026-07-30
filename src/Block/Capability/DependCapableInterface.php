<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * A block that appears or disappears depending on other answers.
 *
 * The condition decides whether the block is there at all, which is a fact
 * about the form rather than about the screen: a block its condition hides is
 * never asked for, drawn or not.
 *
 * {@see DependCapableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface DependCapableInterface {

  /**
   * Decide whether this block is there at all.
   *
   * @param \Closure $when
   *   Returns whether it is.
   *
   * @return static
   *   The block.
   */
  public function when(\Closure $when): static;

  /**
   * Whether this block is there.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isActive(): bool;

}
