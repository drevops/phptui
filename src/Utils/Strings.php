<?php

declare(strict_types=1);

namespace DrevOps\Tui\Utils;

use AlexSkrypnyk\Str2Name\Str2Name;

/**
 * UTF-8 string helpers backed by mbstring when available.
 *
 * The mbstring detection, the byte-level fallbacks behind it and the case
 * folding are the base class's; what is added here is the text handling a
 * terminal needs and a name formatter does not - measuring, slicing and
 * wrapping a line, and filling a template in.
 *
 * @package DrevOps\Tui\Utils
 */
final class Strings extends Str2Name {

  /**
   * Force or reset the mbstring branch selection.
   *
   * @param bool|null $enabled
   *   TRUE to use mbstring, FALSE to use the fallbacks, NULL to re-detect
   *   from the loaded extensions on next use.
   */
  public static function useMbstring(?bool $enabled): void {
    self::$mbstring = $enabled;
  }

  /**
   * Split text into a list of single characters.
   *
   * @param string $text
   *   The text.
   *
   * @return list<string>
   *   The characters.
   */
  public static function split(string $text): array {
    $chars = array_values(self::mbStrSplit($text));

    // The extension-free branch splits with PCRE, which rejects malformed
    // UTF-8 where mbstring substitutes for it and so hands back nothing at
    // all; splitting such input into its bytes keeps the helper total.
    return $chars === [] && $text !== '' ? str_split($text) : $chars;
  }

  /**
   * The length of text in characters.
   *
   * @param string $text
   *   The text.
   *
   * @return int
   *   The number of characters.
   */
  public static function length(string $text): int {
    return self::hasMbstring() ? self::mbStrlen($text) : count(self::split($text));
  }

  /**
   * A portion of text bounded by character offsets.
   *
   * @param string $text
   *   The text.
   * @param int $start
   *   The start offset in characters; negative counts from the end.
   * @param int|null $length
   *   The maximum characters to take; negative leaves that many characters
   *   off the end, NULL takes everything to the end.
   *
   * @return string
   *   The portion.
   */
  public static function substr(string $text, int $start, ?int $length = NULL): string {
    return self::hasMbstring() ? self::mbSubstr($text, $start, $length) : implode('', array_slice(self::split($text), $start, $length));
  }

  /**
   * Replace `{{token}}` placeholders in a template with values.
   *
   * A token is `{{name}}` with optional inner whitespace; one missing from the
   * values, or holding a non-scalar value, resolves to an empty string.
   *
   * @param string $template
   *   The template carrying `{{token}}` placeholders.
   * @param array<string,mixed> $values
   *   The replacement values keyed by token name.
   *
   * @return string
   *   The interpolated string.
   */
  public static function interpolate(string $template, array $values): string {
    return (string) preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function (array $matches) use ($values): string {
      $value = $values[$matches[1]] ?? '';

      return is_scalar($value) ? (string) $value : '';
    }, $template);
  }

  /**
   * Word-wrap text to a column width, breaking on whitespace.
   *
   * Runs of whitespace collapse to a single space and a word longer than the
   * width is hard-split across lines, so every returned line fits the width.
   *
   * @param string $text
   *   The text.
   * @param int $width
   *   The maximum line width in characters.
   *
   * @return list<string>
   *   The wrapped lines; empty when the text has no visible characters.
   */
  public static function wrap(string $text, int $width): array {
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === FALSE || $words === []) {
      return [];
    }

    if ($width < 1) {
      return [implode(' ', $words)];
    }

    $lines = [];
    $current = '';

    foreach ($words as $word) {
      while (self::length($word) > $width) {
        if ($current !== '') {
          $lines[] = $current;
          $current = '';
        }

        $lines[] = self::substr($word, 0, $width);
        $word = self::substr($word, $width);
      }

      if ($current === '') {
        $current = $word;
      }
      elseif (self::length($current) + 1 + self::length($word) <= $width) {
        $current .= ' ' . $word;
      }
      else {
        $lines[] = $current;
        $current = $word;
      }
    }

    if ($current !== '') {
      $lines[] = $current;
    }

    return $lines;
  }

}
