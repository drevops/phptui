<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * The resolved bindings for one scope, narrowed for a single field.
 *
 * A field (or the panel controller) holds one of these and asks it whether a
 * key press means an action - {@see matches()} - instead of testing raw key
 * names. It also answers the reverse question - {@see keysFor()} and
 * {@see primary()} - so key hints can be rendered from the same source of
 * truth and never drift from the active bindings. Instances are produced by
 * {@see KeyMap}; a scope resolves every key to at most one action, so matching
 * is a direct lookup.
 *
 * @package DrevOps\Tui\Input
 */
final readonly class ScopedKeyMap {

  /**
   * Construct a scoped map.
   *
   * @param array<string,\DrevOps\Tui\Input\Action> $byKey
   *   The action each key triggers, keyed by the key's token.
   * @param array<string,list<\DrevOps\Tui\Input\Key>> $byAction
   *   The keys bound to each action, keyed by the action's name.
   */
  public function __construct(
    protected array $byKey = [],
    protected array $byAction = [],
  ) {
  }

  /**
   * Whether a key press triggers an action in this scope.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key pressed.
   * @param \DrevOps\Tui\Input\Action $action
   *   The action to test for.
   *
   * @return bool
   *   TRUE when the key is bound to the action.
   */
  public function matches(Key $key, Action $action): bool {
    return ($this->byKey[$key->token()] ?? NULL) === $action;
  }

  /**
   * The keys bound to an action in this scope, in declaration order.
   *
   * @param \DrevOps\Tui\Input\Action $action
   *   The action.
   *
   * @return list<\DrevOps\Tui\Input\Key>
   *   The keys, empty when the action is unbound here.
   */
  public function keysFor(Action $action): array {
    return $this->byAction[$action->name] ?? [];
  }

  /**
   * The primary (first-declared) key bound to an action, for hint display.
   *
   * @param \DrevOps\Tui\Input\Action $action
   *   The action.
   *
   * @return \DrevOps\Tui\Input\Key|null
   *   The primary key, or NULL when the action is unbound here.
   */
  public function primary(Action $action): ?Key {
    return $this->keysFor($action)[0] ?? NULL;
  }

}
