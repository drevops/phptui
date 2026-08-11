<?php

declare(strict_types=1);

namespace DrevOps\Tui\Input;

/**
 * Parses a raw terminal byte buffer into a list of keys.
 *
 * Recognizes printable characters (including multi-byte UTF-8 sequences),
 * Enter/Backspace/Tab/Space, the Ctrl-C interrupt, bare Escape, CSI and SS3
 * arrows and navigation keys (Home/End/PageUp/PageDown/Delete) with any
 * modifier parameters, and SGR mouse-wheel events. Unrecognized or truncated
 * escape sequences are consumed whole and degrade to Escape, and unknown mouse
 * events are consumed without emitting a key.
 *
 * @package DrevOps\Tui\Input
 */
class KeyParser {

  /**
   * Parse a byte buffer into keys.
   *
   * @param string $bytes
   *   The raw bytes.
   *
   * @return \DrevOps\Tui\Input\Key[]
   *   The parsed keys.
   */
  public function parse(string $bytes): array {
    $keys = [];
    $length = strlen($bytes);
    $i = 0;

    while ($i < $length) {
      if ($bytes[$i] === "\033") {
        [$key, $consumed] = $this->parseEscape($bytes, $i);

        if ($key instanceof Key) {
          $keys[] = $key;
        }

        $i += $consumed;
        continue;
      }

      // A multi-byte UTF-8 sequence is one typed character, not several keys.
      $span = $this->utf8Span($bytes, $i);

      if ($span > 1) {
        $keys[] = Key::char(substr($bytes, $i, $span));
        $i += $span;
        continue;
      }

      $keys[] = $this->parseSimple($bytes[$i]);
      $i++;
    }

    return $keys;
  }

  /**
   * The byte length of the UTF-8 sequence starting at an offset.
   *
   * @param string $bytes
   *   The raw bytes.
   * @param int $start
   *   The offset of the candidate lead byte.
   *
   * @return int
   *   The sequence length (2-4) when a complete, well-formed multi-byte
   *   sequence starts here; 1 for ASCII, a stray byte or a truncated sequence.
   */
  protected function utf8Span(string $bytes, int $start): int {
    $lead = ord($bytes[$start]);

    $span = match (TRUE) {
      $lead >= 0xC2 && $lead <= 0xDF => 2,
      $lead >= 0xE0 && $lead <= 0xEF => 3,
      $lead >= 0xF0 && $lead <= 0xF4 => 4,
      default => 1,
    };

    if ($span === 1 || $start + $span > strlen($bytes)) {
      return 1;
    }

    for ($i = 1; $i < $span; $i++) {
      $byte = ord($bytes[$start + $i]);

      if ($byte < 0x80 || $byte > 0xBF) {
        return 1;
      }
    }

    return $span;
  }

  /**
   * Parse a single non-escape byte.
   *
   * @param string $char
   *   The byte.
   *
   * @return \DrevOps\Tui\Input\Key
   *   The key.
   */
  protected function parseSimple(string $char): Key {
    return match ($char) {
      "\r", "\n" => Key::named(KeyName::Enter),
      "\x7f", "\x08" => Key::named(KeyName::Backspace),
      "\t" => Key::named(KeyName::Tab),
      ' ' => Key::named(KeyName::Space),
      "\x03" => Key::named(KeyName::Interrupt),
      default => Key::char($char),
    };
  }

  /**
   * Parse an escape sequence starting at an offset.
   *
   * @param string $bytes
   *   The raw bytes.
   * @param int $start
   *   The offset of the ESC byte.
   *
   * @return array{\DrevOps\Tui\Input\Key|null,int}
   *   The key (or NULL) and the number of bytes consumed.
   */
  protected function parseEscape(string $bytes, int $start): array {
    $length = strlen($bytes);

    if ($start + 1 >= $length) {
      return [Key::named(KeyName::Escape), 1];
    }

    // SS3 (ESC O <final>): the application-cursor-keys form of the arrows and
    // Home/End; the final byte matches its CSI counterpart.
    if ($bytes[$start + 1] === 'O') {
      if ($start + 2 >= $length) {
        return [Key::named(KeyName::Escape), 2];
      }

      $name = $this->csiName($bytes[$start + 2], '');

      return [Key::named($name ?? KeyName::Escape), 3];
    }

    if ($bytes[$start + 1] !== '[') {
      return [Key::named(KeyName::Escape), 1];
    }

    if ($start + 2 < $length && $bytes[$start + 2] === '<') {
      return $this->parseMouse($bytes, $start);
    }

    // Accept the full CSI parameter byte range (digits, ";" and friends) so a
    // modifier sequence such as Shift-Up (ESC [ 1 ; 2 A) is consumed whole
    // rather than leaking its tail as typed characters.
    $j = $start + 2;
    $params = '';

    while ($j < $length && ord($bytes[$j]) >= 0x30 && ord($bytes[$j]) <= 0x3F) {
      $params .= $bytes[$j];
      $j++;
    }

    if ($j >= $length) {
      // Truncated at a read boundary: swallow the scanned prefix rather than
      // leaking it as typed characters.
      return [Key::named(KeyName::Escape), $j - $start];
    }

    $name = $this->csiName($bytes[$j], $params);

    return [Key::named($name ?? KeyName::Escape), $j - $start + 1];
  }

  /**
   * Resolve a CSI final byte (and parameters) to a key name.
   *
   * @param string $final
   *   The final byte.
   * @param string $params
   *   The numeric parameters.
   *
   * @return \DrevOps\Tui\Input\KeyName|null
   *   The key name, or NULL when unrecognized.
   */
  protected function csiName(string $final, string $params): ?KeyName {
    return match ($final) {
      'A' => KeyName::Up,
      'B' => KeyName::Down,
      'C' => KeyName::Right,
      'D' => KeyName::Left,
      'H' => KeyName::Home,
      'F' => KeyName::End,
      '~' => $this->tildeName($params),
      default => NULL,
    };
  }

  /**
   * Resolve a `CSI <n> ~` parameter to a key name.
   *
   * @param string $params
   *   The parameter bytes; a modifier after ";" does not change the key.
   *
   * @return \DrevOps\Tui\Input\KeyName|null
   *   The key name, or NULL when unrecognized.
   */
  protected function tildeName(string $params): ?KeyName {
    return match (explode(';', $params)[0]) {
      '1', '7' => KeyName::Home,
      '4', '8' => KeyName::End,
      '3' => KeyName::Delete,
      '5' => KeyName::PageUp,
      '6' => KeyName::PageDown,
      default => NULL,
    };
  }

  /**
   * Parse an SGR mouse sequence starting at an offset.
   *
   * @param string $bytes
   *   The raw bytes.
   * @param int $start
   *   The offset of the ESC byte.
   *
   * @return array{\DrevOps\Tui\Input\Key|null,int}
   *   The key (or NULL) and the number of bytes consumed.
   */
  protected function parseMouse(string $bytes, int $start): array {
    $length = strlen($bytes);
    $j = $start + 3;
    $data = '';

    while ($j < $length && $bytes[$j] !== 'M' && $bytes[$j] !== 'm') {
      $data .= $bytes[$j];
      $j++;
    }

    if ($j >= $length) {
      // Truncated at a read boundary: swallow the scanned prefix rather than
      // leaking it as typed characters.
      return [Key::named(KeyName::Escape), $j - $start];
    }

    $parts = explode(';', $data);
    $button = (int) $parts[0];
    $name = match ($button) {
      64 => KeyName::MouseWheelUp,
      65 => KeyName::MouseWheelDown,
      default => NULL,
    };

    return [$name instanceof KeyName ? Key::named($name) : NULL, $j - $start + 1];
  }

}
