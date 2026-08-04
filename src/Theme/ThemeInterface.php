<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

use DrevOps\Tui\Input\KeyName;

/**
 * A theme, in what belongs to no one part of what it draws.
 *
 * Everything a theme styles is an element, and every element belongs to the
 * block that declares it - so a theme is written as one
 * `*ElementsInterface` implementation per block and nothing here. What is left
 * is what no block could own: the width they all lay out against, and how this
 * theme writes a key - plus the width to lay out to when no terminal has been
 * measured, which is a number every theme answers with rather than a method.
 *
 * Both methods take what an element takes: plain scalars and enum cases, never
 * a value object and never anything a form is holding.
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
   * The width, in columns, a theme lays out to when nothing else says.
   *
   * A frame narrow enough to read a line of prose across, which is what a
   * theme is built with wherever no terminal has been measured. It belongs to
   * the contract rather than to any one theme, so the plumbing that builds a
   * theme and the primitives that draw beside a form all reach the same number
   * without naming a class that ships.
   */
  public const int DEFAULT_WIDTH = 76;

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
   * the same way. A named key travels as its name and a typed one as the
   * character, so this takes what an element takes - an enum or a scalar - and
   * never the value object an input layer carries a key around in.
   *
   * @param \DrevOps\Tui\Input\KeyName|string $key
   *   The named key, or the character a typed key writes.
   *
   * @return string
   *   The key as this theme writes it.
   */
  public function keyGlyph(KeyName|string $key): string;

}
