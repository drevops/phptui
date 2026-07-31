<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Condition\ConditionInterface;

/**
 * A block that appears or disappears depending on other answers.
 *
 * The condition decides whether the block is there at all, which is a fact
 * about the form rather than about the screen: a block its condition hides is
 * never asked for, drawn or not.
 *
 * It is answered against the answers collected so far, because other answers
 * are the only thing a dependency can be about.
 *
 * {@see DependCapableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface DependCapableInterface {

  /**
   * Decide whether this block is there at all.
   *
   * @param \Closure|\DrevOps\Tui\Condition\ConditionInterface $when
   *   A declared condition matched against the answers, or an
   *   `fn (array<string,mixed> $answers): bool` deciding for itself.
   *
   * @return static
   *   The block.
   */
  public function when(\Closure|ConditionInterface $when): static;

  /**
   * Whether this block is there.
   *
   * @param array<string,mixed> $answers
   *   The answers collected so far, keyed by the id each is held under.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isActive(array $answers = []): bool;

}
