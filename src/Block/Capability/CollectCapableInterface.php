<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * A block that holds a value which ends up in the result.
 *
 * It is the only capability that survives with no screen at all: the value
 * arrives, it is offered rather than taken on trust, and what was accepted is
 * what the result carries under this block's id.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface CollectCapableInterface {

  /**
   * The id this block's answer is keyed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string;

  /**
   * The answer this block contributes to the result.
   *
   * @return mixed
   *   The value, or NULL until one is accepted.
   */
  public function value(): mixed;

  /**
   * Seed the answer this block starts with.
   *
   * @param mixed $value
   *   The value.
   *
   * @return static
   *   The block.
   */
  public function default(mixed $value): static;

  /**
   * Offer a value, or the draft when none is given.
   *
   * @param mixed $value
   *   The value, or omitted to offer whatever is being typed.
   *
   * @return bool
   *   Whether it was accepted. A refused value leaves the answer where it was.
   */
  public function accept(mixed $value = NULL): bool;

}
