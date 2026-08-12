<?php

declare(strict_types=1);

namespace DrevOps\Tui\Answers;

use DrevOps\Tui\Translation\Translator;

/**
 * Renders an answer value readably, one rule for every display surface.
 *
 * A boolean reads as a translated yes/no, a list joins its scalar items with
 * commas, a scalar casts to its string, and anything else renders empty. The
 * panel rows, the grid previews and the answer summary all route through this
 * one rendering, so a value never reads differently between surfaces. The
 * fixed-length mask conceals both a secret's value and its length, whatever
 * glyph the surface masks with.
 *
 * @package DrevOps\Tui\Answers
 */
final class ValueFormatter {

  /**
   * The fixed mask length for secret values, concealing their real length.
   */
  public const int MASK_LENGTH = 8;

  /**
   * Render a value readably.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The rendered value.
   */
  public static function format(mixed $value): string {
    if (is_bool($value)) {
      return $value ? Translator::t('yes') : Translator::t('no');
    }

    if (is_array($value)) {
      return implode(', ', array_map(static fn(mixed $item): string => is_scalar($item) ? (string) $item : '', $value));
    }

    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * A fixed-length mask concealing a secret value.
   *
   * @param string $glyph
   *   The masking glyph (e.g. "*" or the theme's mask symbol).
   *
   * @return string
   *   The mask.
   */
  public static function mask(string $glyph): string {
    return str_repeat($glyph, self::MASK_LENGTH);
  }

}
