<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Condition\ConditionInterface;

/**
 * Dependency behaviour: whether a block is there at all.
 *
 * @package DrevOps\Tui\Block\Capability
 */
trait DependCapableTrait {

  /**
   * What decides whether this block is there at all.
   */
  protected \Closure|ConditionInterface|null $when = NULL;

  /**
   * {@inheritdoc}
   */
  public function when(\Closure|ConditionInterface $when): static {
    $this->when = $when;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(array $answers = []): bool {
    if ($this->when instanceof ConditionInterface) {
      return $this->when->matches($answers);
    }

    // A closure that declares no parameter ignores what it is handed, so both
    // shapes of condition are called the same way.
    return !$this->when instanceof \Closure || (bool) ($this->when)($answers);
  }

}
