<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\ScopedKeyMap;

/**
 * A block that says which keys apply while it is in play.
 *
 * Keys travel inward: one goes to the innermost block that binds it and only
 * outward from there. That single rule is why the same key can reach a block in
 * one state and pass straight through it in another, without anything being
 * written as an exception.
 *
 * A block lists no keys of its own. It answers in a scope, and the bindings
 * resolved for that scope are what it binds - so retuning a key is one
 * declaration for the whole form rather than one per block that draws it.
 *
 * {@see BindCapableTrait} carries the default implementation.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface BindCapableInterface {

  /**
   * Answer to a set of bindings rather than to the default ones.
   *
   * @param \DrevOps\Tui\Input\KeyMap $keys
   *   The resolved bindings for the whole form.
   *
   * @return static
   *   The block.
   */
  public function bind(KeyMap $keys): static;

  /**
   * The keys that apply while this block is in play.
   *
   * @return \DrevOps\Tui\Input\ScopedKeyMap
   *   The bindings resolved for this block's scope.
   */
  public function bindings(): ScopedKeyMap;

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

  /**
   * What the keys that stop here do, as labelled fragments.
   *
   * The same fragments a legend is written from, so the line advertising a key
   * and the block answering it can never disagree about which keys are live.
   *
   * @return list<\DrevOps\Tui\Input\Hint>
   *   The fragments, in the order they are advertised.
   */
  public function hints(): array;

}
