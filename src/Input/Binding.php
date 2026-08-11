<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * One authored binding: an action and the keys that trigger it in a scope.
 *
 * Presets and consumer overrides both declare their bindings in this form.
 * A key may be a {@see KeyName} for a named key, a single-character string
 * for a printable key, or a {@see Key}; {@see KeyMap} normalizes all three
 * forms to {@see Key} when it resolves. Two bindings for the same scope and
 * action do not merge: the later one wins, so a consumer replaces a preset's
 * binding by re-declaring it.
 *
 * @package DrevOps\Tui\Input
 */
final readonly class Binding {

  /**
   * The keys bound to the action, before normalisation.
   *
   * @var list<\DrevOps\Tui\Input\Key|\DrevOps\Tui\Input\KeyName|string>
   */
  public array $keys;

  /**
   * Construct a binding.
   *
   * @param \DrevOps\Tui\Input\Scope $scope
   *   The scope the binding applies in.
   * @param \DrevOps\Tui\Input\Action $action
   *   The action the keys trigger.
   * @param \DrevOps\Tui\Input\Key|\DrevOps\Tui\Input\KeyName|string ...$keys
   *   The keys bound to the action.
   */
  public function __construct(
    public Scope $scope,
    public Action $action,
    Key|KeyName|string ...$keys,
  ) {
    $this->keys = array_values($keys);
  }

}
