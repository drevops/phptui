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
 * Whether an answer is owed at all, what shape it has, and how an accepted one
 * is normalized are all facts about the value rather than about the screen, so
 * they belong here beside it.
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

  /**
   * Demand an answer, refusing an empty one.
   *
   * @param bool $required
   *   Whether an answer is owed.
   * @param string $message
   *   What is said when it is missing; empty derives it from the label.
   *
   * @return static
   *   The block.
   */
  public function required(bool $required = TRUE, string $message = ''): static;

  /**
   * Whether an answer is owed.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isRequired(): bool;

  /**
   * What is said when a required answer is missing, else NULL.
   *
   * A whole sentence rather than a fragment, because the message is the form
   * author's to declare and so cannot be framed by a caller.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The message, or NULL when an answer is not owed or one was given.
   */
  public function requiredViolation(mixed $value): ?string;

  /**
   * Normalize an accepted value before it becomes the answer.
   *
   * @param \Closure $transform
   *   An `fn (mixed $value): mixed` returning the value to hold.
   *
   * @return static
   *   The block.
   */
  public function transform(\Closure $transform): static;

  /**
   * Hold several values as a list rather than one.
   *
   * @param bool $multiple
   *   Whether several are held.
   *
   * @return static
   *   The block.
   */
  public function multiple(bool $multiple = TRUE): static;

  /**
   * Whether the answer is a list rather than a single value.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function collectsList(): bool;

}
