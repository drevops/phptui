<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A single interactive field collector driven one key at a time.
 *
 * @package DrevOps\Tui\Widget
 */
interface WidgetInterface {

  /**
   * Process one key press, mutating the widget state.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to process.
   */
  public function handle(Key $key): void;

  /**
   * Give the widget the resolved bindings for its scope.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The scoped key bindings.
   *
   * @return static
   *   The widget, for chaining.
   */
  public function setKeys(ScopedKeyMap $keys): static;

  /**
   * Give the widget its validator and transformer.
   *
   * @param \Closure|null $validate
   *   The validator `fn(mixed $value): ?string` returning an error message, or
   *   NULL when the value is valid; a NULL validator accepts every value.
   * @param \Closure|null $transform
   *   The transformer `fn(mixed $value): mixed` applied on accept, or NULL to
   *   accept values as they are.
   *
   * @return static
   *   The widget, for chaining.
   */
  public function setHandlers(?\Closure $validate = NULL, ?\Closure $transform = NULL): static;

  /**
   * Whether a valid value has been accepted.
   */
  public function isComplete(): bool;

  /**
   * Whether the widget was cancelled (Escape).
   */
  public function isCancelled(): bool;

  /**
   * The current value.
   *
   * @return mixed
   *   The typed value (string, string[] or bool depending on the widget).
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
   * A rendering of the current state, using the theme's glyphs.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying Unicode or ASCII glyphs.
   *
   * @return string
   *   The rendered view.
   */
  public function view(ThemeInterface $theme): string;

  /**
   * The key-hint fragments for this widget, in display order.
   *
   * Each fragment pairs a label with the actions whose live keys illustrate it;
   * the footer renders them against the widget's bindings. This is the widget's
   * own contribution to the contextual help footer.
   *
   * @return list<\DrevOps\Tui\Input\Hint>
   *   The ordered hint fragments.
   */
  public function hints(): array;

  /**
   * The bindings the widget answers to.
   *
   * The same map that resolves a keystroke also resolves a hint into the key
   * that illustrates it, so the two can never disagree about which keys are
   * live.
   *
   * @return \DrevOps\Tui\Input\ScopedKeyMap
   *   The scoped bindings.
   */
  public function keys(): ScopedKeyMap;

}
