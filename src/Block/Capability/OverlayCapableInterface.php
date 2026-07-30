<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * A block that draws over everything else rather than replacing it.
 *
 * It is the same block either way: overlaying changes what is behind it, and
 * nothing about the block itself.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface OverlayCapableInterface {

  /**
   * Draw over what is behind rather than replacing it.
   *
   * @return static
   *   The block.
   */
  public function modal(): static;

  /**
   * Whether this block draws over what is behind it.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function isModal(): bool;

}
