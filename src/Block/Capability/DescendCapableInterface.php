<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Capability;

/**
 * A block you go into: the screen becomes its contents, and you can come back.
 *
 * Going in replaces the screen and grows the trail; leaving restores both. It
 * is the capability nothing else has - a container holds things and an
 * arrangement places them, but neither is somewhere you go.
 *
 * @package DrevOps\PhpTui\Block\Capability
 */
interface DescendCapableInterface {

  /**
   * Mark this as the block you are currently in.
   *
   * @return static
   *   The block.
   */
  public function enter(): static;

  /**
   * Leave this block, so it draws as a row again.
   *
   * @return static
   *   The block.
   */
  public function leave(): static;

  /**
   * Whether this is the block you are currently in.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isEntered(): bool;

}
