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
   * Whether this block is off the screen because the answers took it off.
   */
  protected bool $hidden = FALSE;

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
  public function condition(): \Closure|ConditionInterface|null {
    return $this->when;
  }

  /**
   * The declared rule deciding whether this block is there at all.
   *
   * A rule the block decides for itself cannot be read, only asked, so it is
   * absent here: anything describing the form has to be able to say which
   * answers a dependency is about.
   *
   * @return \DrevOps\Tui\Condition\ConditionInterface|null
   *   The rule, or NULL when the block is always there or decides for itself.
   */
  public function rule(): ?ConditionInterface {
    return $this->when instanceof ConditionInterface ? $this->when : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hide(): static {
    $this->hidden = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function reveal(): static {
    $this->hidden = FALSE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden(): bool {
    return $this->hidden;
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
