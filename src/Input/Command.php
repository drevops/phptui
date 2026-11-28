<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

/**
 * One caller binding: keys that run a caller handler on the panel hub.
 *
 * A {@see Binding} maps keys to the fixed {@see Action} set; a command maps
 * keys to a handler of the caller's own and names what it does for the hint
 * line. Commands are declared in the same list as bindings and resolve in the
 * navigation scope only, so they apply while the panel answers keys and never
 * while a field is open. {@see KeyMap} rejects a command key that is already
 * bound in that scope, to an action or to another command.
 *
 * @package DrevOps\PhpTui\Input
 */
final readonly class Command {

  /**
   * The keys that run the handler, before normalisation.
   *
   * @var list<\DrevOps\PhpTui\Input\Key|\DrevOps\PhpTui\Input\KeyName|string>
   */
  public array $keys;

  /**
   * Construct a command.
   *
   * @param string $label
   *   The English source label the hint line shows (e.g. "save"); translated
   *   to the active language.
   * @param \Closure(\DrevOps\PhpTui\Answers\Answers): void $handler
   *   The handler, called with the answers as they stand.
   * @param \DrevOps\PhpTui\Input\Key|\DrevOps\PhpTui\Input\KeyName|string ...$keys
   *   The keys that run it.
   */
  public function __construct(
    public string $label,
    public \Closure $handler,
    Key|KeyName|string ...$keys,
  ) {
    $this->keys = array_values($keys);
  }

}
