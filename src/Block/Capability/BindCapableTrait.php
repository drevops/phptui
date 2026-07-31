<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;

/**
 * Binding behaviour: the keys that apply while a block is in play.
 *
 * @package DrevOps\Tui\Block\Capability
 */
trait BindCapableTrait {

  /**
   * The bindings the whole form answers to, once it has been given some.
   */
  protected ?KeyMap $formKeys = NULL;

  /**
   * {@inheritdoc}
   */
  public function bind(KeyMap $keys): static {
    $this->formKeys = $keys;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function bindings(): ScopedKeyMap {
    return $this->keyMap()->scope($this->keyScope());
  }

  /**
   * {@inheritdoc}
   */
  public function binds(Key $key): bool {
    return $this->boundAction($key) instanceof Action;
  }

  /**
   * The bindings this block resolves its scope against.
   *
   * @return \DrevOps\Tui\Input\KeyMap
   *   The bindings it was given, else the default preset.
   */
  protected function keyMap(): KeyMap {
    return $this->formKeys ??= KeyMapManager::create();
  }

  /**
   * What a key means here.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return \DrevOps\Tui\Input\Action|null
   *   The action it triggers, or NULL when it triggers none here.
   */
  protected function boundAction(Key $key): ?Action {
    $bindings = $this->bindings();

    foreach (Action::cases() as $action) {
      if ($bindings->matches($key, $action)) {
        return $action;
      }
    }

    return NULL;
  }

  /**
   * The scope this block's keys resolve in.
   *
   * @return \DrevOps\Tui\Input\Scope
   *   The scope.
   */
  abstract protected function keyScope(): Scope;

}
