<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A hue-free theme: contrast from weight, grey levels and reverse video.
 *
 * A monochrome palette selectable by name ("mono"). Accents are bold, matches
 * invert, and values and the border sit on the 256-colour grey ramp - so the
 * chrome reads on any background without relying on colour perception. The
 * semantic red error is inherited on purpose. It declares its colours by
 * overriding the appearance atoms directly and inherits the default theme's
 * layout, glyphs and dark/light mode.
 *
 * @package DrevOps\Tui\Theme
 */
class MonoTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Silver) : Sgr::of(Sgr::Ash), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hint(string $text, bool $selected = FALSE): string {
    // The inherited guidance hue would be the one colour in a hue-free theme,
    // so the voice moves along the grey ramp instead: a step away from the
    // description it must not be mistaken for.
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Italic, Sgr::Pewter) : Sgr::of(Sgr::Italic, Sgr::Ash), $selected), $this->linkify($text));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint(Sgr::of(Sgr::Reverse), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gunmetal) : Sgr::of(Sgr::Pewter), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black), $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black), $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black), $this->unicode ? '█' : '|');
  }

}
