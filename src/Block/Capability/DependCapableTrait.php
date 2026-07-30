<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * Dependency behaviour: whether a block is there at all.
 *
 * @package DrevOps\Tui\Block\Capability
 */
trait DependCapableTrait {

  /**
   * What decides whether this block is there at all.
   *
   * @var \Closure(): bool|null
   */
  protected ?\Closure $when = NULL;

  /**
   * {@inheritdoc}
   */
  public function when(\Closure $when): static {
    $this->when = $when;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return !$this->when instanceof \Closure || ($this->when)();
  }

}
