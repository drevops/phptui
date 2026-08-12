<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

/**
 * How a password field renders its buffer while editing.
 *
 * The stored value is never affected; this only controls the live view.
 *
 * @package DrevOps\PhpTui\Field
 */
enum PasswordDisplay {

  // No glyphs, caret only - conceals both the value and its length.
  case Hidden;

  // One mask glyph per character - conceals the value but reveals its length.
  case Masked;

  // The literal characters.
  case Plaintext;

  /**
   * The next display in the reveal cycle.
   *
   * @return self
   *   Hidden to Masked to Plaintext and back to Hidden, so a toggle stepping
   *   from the Masked default reveals the value on its first press.
   */
  public function next(): self {
    return match ($this) {
      self::Hidden => self::Masked,
      self::Masked => self::Plaintext,
      self::Plaintext => self::Hidden,
    };
  }

}
