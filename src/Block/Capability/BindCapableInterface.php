<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;

/**
 * A block that says which keys apply while it is in play.
 *
 * Keys travel inward: one goes to the innermost block that binds it and only
 * outward from there. That single rule is why the same key can reach a block in
 * one state and pass straight through it in another, without anything being
 * written as an exception.
 *
 * {@see BindCapableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface BindCapableInterface {

  /**
   * Declare the keys that apply while this block is in play.
   *
   * @param \DrevOps\Tui\Input\KeyName ...$keys
   *   The keys.
   *
   * @return static
   *   The block.
   */
  public function bind(KeyName ...$keys): static;

  /**
   * The keys that apply while this block is in play.
   *
   * @return list<\DrevOps\Tui\Input\KeyName>
   *   The keys, in the order they were declared.
   */
  public function bindings(): array;

  /**
   * Whether a key stops at this block rather than travelling outward.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function binds(Key $key): bool;

}
