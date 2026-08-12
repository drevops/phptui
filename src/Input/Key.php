<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

/**
 * A single key press: either a named special key or a printable character.
 *
 * @package DrevOps\PhpTui\Input
 */
final readonly class Key {

  /**
   * Construct a key.
   *
   * @param \DrevOps\PhpTui\Input\KeyName|null $name
   *   The named special key, or NULL for a printable character.
   * @param string|null $char
   *   The printable character, or NULL for a named key.
   */
  protected function __construct(
    public ?KeyName $name = NULL,
    public ?string $char = NULL,
  ) {
  }

  /**
   * Create a named special key.
   *
   * @param \DrevOps\PhpTui\Input\KeyName $name
   *   The key name.
   */
  public static function named(KeyName $name): self {
    return new self($name);
  }

  /**
   * Create a printable-character key.
   *
   * @param string $char
   *   A single printable character.
   */
  public static function char(string $char): self {
    return new self(NULL, $char);
  }

  /**
   * Whether this key is a printable character.
   */
  public function isChar(): bool {
    return $this->char !== NULL;
  }

  /**
   * Whether this key is the given named key.
   *
   * @param \DrevOps\PhpTui\Input\KeyName $name
   *   The name to compare.
   */
  public function is(KeyName $name): bool {
    return $this->name === $name;
  }

  /**
   * Whether this key is the same key as another.
   *
   * @param \DrevOps\PhpTui\Input\Key $other
   *   The key to compare.
   *
   * @return bool
   *   TRUE when both are the same named key or the same character.
   */
  public function equals(Key $other): bool {
    return $this->token() === $other->token();
  }

  /**
   * A stable token identifying this key, usable as an array key.
   *
   * @return string
   *   The token.
   */
  public function token(): string {
    return $this->name instanceof KeyName ? 'name:' . $this->name->name : 'char:' . $this->char;
  }

  /**
   * A readable label for the key, for error messages and key hints.
   *
   * @return string
   *   The key name for a named key; a control character rendered as
   *   "ctrl-<letter>"; otherwise the character itself.
   */
  public function label(): string {
    if ($this->name instanceof KeyName) {
      return $this->name->name;
    }

    $char = (string) $this->char;

    return $char !== '' && ord($char) < 0x20 ? 'ctrl-' . strtolower(chr(ord($char) + 0x40)) : $char;
  }

}
