<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Input\Key;

/**
 * A theme, in the two things that belong to no one part of what it draws.
 *
 * Everything a theme styles is an element, and every element belongs to the
 * block that declares it - so a theme is written as one
 * `*ElementsInterface` implementation per block and nothing here. What is left
 * is what no block could own: the width they all lay out against, and how this
 * theme writes a key.
 *
 * A theme says what it can do rather than being asked: declaring
 * {@see \DrevOps\Tui\Theme\Capability\ColorSchemeCapableInterface},
 * {@see \DrevOps\Tui\Theme\Capability\UnicodeCapableInterface} or any other
 * capability is what grants the facility that goes with it. A theme that
 * declares none still draws - it hands back the strings it was given - which is
 * why a form renders in a terminal that supports nothing.
 *
 * @code
 * final class OrchardTheme extends AbstractTheme implements ColorSchemeCapableInterface {
 *   use ColorSchemeCapableTrait;
 *
 *   public function fieldLabel(string $text): string { return $this->paint(Sgr::of(Sgr::Bold, Sgr::Green), $text); }
 * }
 * @endcode
 *
 * The {@see Mode}, {@see Spacing} and {@see Border} enums carry the display
 * options a consumer passes in the theme options array (as enum cases or their
 * string values).
 *
 * @package DrevOps\Tui\Theme
 */
interface ThemeInterface {

  /**
   * The width, in columns, available for the content a theme lays out.
   *
   * The frame's inner width, already less any border and gutter. It belongs to
   * no block because every one of them measures against it and none may ask
   * where its own space ends: a card wraps to it, a badge column ends at it,
   * and a field drops a line it has no room for rather than wrapping it into
   * fragments. Asking the theme is what keeps one width across the whole frame.
   *
   * @return int
   *   The width.
   */
  public function contentWidth(): int;

  /**
   * How this theme writes one key.
   *
   * Vocabulary rather than styling, which is why it is here and not on a block:
   * the legend that lists the live bindings, the field that names a key in a
   * prompt and the notice that says how to quit all have to spell the same key
   * the same way. It is handed a key and nothing else, so it never reaches for
   * anything a form is holding.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return string
   *   The key as this theme writes it.
   */
  public function keyGlyph(Key $key): string;

}
