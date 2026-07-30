<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;

/**
 * Binding behaviour: the keys that apply while a block is in play.
 *
 * @package DrevOps\Tui\Block\Capability
 */
trait BindCapableTrait {

  /**
   * The keys this block binds, in the order they were declared.
   *
   * @var list<\DrevOps\Tui\Input\KeyName>
   */
  protected array $bindings = [];

  /**
   * {@inheritdoc}
   */
  public function bind(KeyName ...$keys): static {
    foreach ($keys as $key) {
      // A key bound twice is still one binding, so the second declaration
      // leaves the order it was first given in alone.
      if (!in_array($key, $this->bindings, TRUE)) {
        $this->bindings[] = $key;
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function bindings(): array {
    return $this->bindings;
  }

  /**
   * {@inheritdoc}
   */
  public function binds(Key $key): bool {
    return in_array($key->name, $this->bindings, TRUE);
  }

}
