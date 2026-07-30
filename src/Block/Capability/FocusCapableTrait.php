<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * Focus behaviour: whether the cursor is on a block.
 *
 * @package DrevOps\Tui\Block\Capability
 */
trait FocusCapableTrait {

  /**
   * Whether the cursor is on this block.
   */
  protected bool $focused = FALSE;

  /**
   * {@inheritdoc}
   */
  public function focus(): static {
    $this->focused = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function blur(): static {
    $this->focused = FALSE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFocused(): bool {
    return $this->focused;
  }

}
