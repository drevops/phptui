<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * A hue-free theme: contrast from weight, grey levels and reverse video.
 *
 * A monochrome palette selectable by name ("mono"). Accents are bold, matches
 * invert, and values and the border sit on the 256-colour grey ramp - so the
 * chrome reads on any background without relying on colour perception. The
 * semantic red error is inherited on purpose. It states its colours and
 * inherits every element from the default theme, including its dark/light mode.
 *
 * @package DrevOps\PhpTui\Theme
 */
class MonoTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return $this->isDark ? Sgr::of(Sgr::Bold, Sgr::BrightWhite) : Sgr::of(Sgr::Bold, Sgr::Black);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Silver) : Sgr::of(Sgr::Ash), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function guidance(): string {
    // The inherited guidance hue would be the one colour in a hue-free theme,
    // so the voice moves along the grey ramp instead: a step away from the
    // description it must not be mistaken for.
    return $this->isDark ? Sgr::of(Sgr::Italic, Sgr::Pewter) : Sgr::of(Sgr::Italic, Sgr::Ash);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gunmetal) : Sgr::of(Sgr::Pewter), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldOptionMatch(string $text): string {
    return $this->paint(Sgr::of(Sgr::Reverse), $text);
  }

}
