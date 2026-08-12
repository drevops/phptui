<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * A cool, vivid theme: violet accents, green values, pink highlights.
 *
 * A curated 256-colour palette selectable by name ("midnight"). It states its
 * colours and inherits every element from the default theme, so it renders
 * across every field and degrades to plain text when colour is off.
 *
 * @package DrevOps\PhpTui\Theme
 */
class MidnightTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return $this->isDark ? Sgr::of(Sgr::Bold, Sgr::Violet) : Sgr::of(Sgr::Bold, Sgr::Indigo);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Jade) : Sgr::of(Sgr::Forest), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Pink) : Sgr::of(Sgr::Fuchsia), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Purple) : Sgr::of(Sgr::Slate), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldOptionMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Pink) : Sgr::of(Sgr::Fuchsia), $text);
  }

}
