<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\ScopedKeyMap;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * A single interactive field collector driven one key at a time.
 *
 * It collects and it offers; it never decides whether what it collected stands.
 * Measuring an offered value belongs to whatever holds the answer, so nothing
 * here refuses one.
 *
 * @package DrevOps\PhpTui\Field
 */
interface FieldInterface {

  /**
   * Process one key press, mutating the field state.
   *
   * @param \DrevOps\PhpTui\Input\Key $key
   *   The key to process.
   */
  public function handle(Key $key): void;

  /**
   * Give the field the resolved bindings for its scope.
   *
   * @param \DrevOps\PhpTui\Input\ScopedKeyMap $keys
   *   The scoped key bindings.
   *
   * @return static
   *   The field, for chaining.
   */
  public function setKeys(ScopedKeyMap $keys): static;

  /**
   * Whether a value has been offered as the answer.
   */
  public function isComplete(): bool;

  /**
   * Whether the field was cancelled (Escape).
   */
  public function isCancelled(): bool;

  /**
   * The current value.
   *
   * @return mixed
   *   The typed value (string, string[] or bool depending on the field).
   */
  public function value(): mixed;

  /**
   * The current validation error, if any.
   *
   * @return string|null
   *   The error message, or NULL when there is none.
   */
  public function error(): ?string;

  /**
   * Show why the value this field offered was refused.
   *
   * A field offers a value and never decides whether it stands, so the reason
   * arrives from outside; it is drawn where the field says what an answer must
   * be, because a reason and an expectation never both apply.
   *
   * @param string $reason
   *   The reason.
   *
   * @return static
   *   The field, for chaining.
   */
  public function refused(string $reason): static;

  /**
   * A rendering of the current state, using the theme's glyphs.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme supplying Unicode or ASCII glyphs.
   *
   * @return string
   *   The rendered view.
   */
  public function view(ThemeInterface $theme): string;

  /**
   * The key-hint fragments for this field, in display order.
   *
   * Each fragment pairs a label with the actions whose live keys illustrate it;
   * the footer renders them against the field's bindings. This is the field's
   * own contribution to the contextual help footer.
   *
   * @return list<\DrevOps\PhpTui\Input\Hint>
   *   The ordered hint fragments.
   */
  public function hints(): array;

  /**
   * The bindings the field answers to.
   *
   * The same map that resolves a keystroke also resolves a hint into the key
   * that illustrates it, so the two can never disagree about which keys are
   * live.
   *
   * @return \DrevOps\PhpTui\Input\ScopedKeyMap
   *   The scoped bindings.
   */
  public function keys(): ScopedKeyMap;

}
