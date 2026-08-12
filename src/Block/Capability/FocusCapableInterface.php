<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Capability;

/**
 * A block you can move onto, so your keys then act on it.
 *
 * Focus is what decides which block is innermost when a key arrives, and it
 * comes apart from both holding a value and doing something: the cursor moves
 * between the blocks that claim it and skips the ones that do not.
 *
 * {@see FocusCapableTrait} carries the default implementation.
 *
 * @package DrevOps\PhpTui\Block\Capability
 */
interface FocusCapableInterface {

  /**
   * Move onto this block.
   *
   * @return static
   *   The block.
   */
  public function focus(): static;

  /**
   * Move off this block.
   *
   * @return static
   *   The block.
   */
  public function blur(): static;

  /**
   * Whether the cursor is on this block.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isFocused(): bool;

}
