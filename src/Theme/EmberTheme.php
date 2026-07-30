<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A warm, retro theme: burnt-orange accents, olive values, gold highlights.
 *
 * A curated 256-colour palette selectable by name ("ember"). It declares its
 * colours by overriding the appearance atoms directly and inherits the default
 * theme's layout, glyphs and dark/light mode, so it renders across every field
 * and degrades to plain text when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class EmberTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Olive) : Sgr::of(Sgr::Khaki), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gold) : Sgr::of(Sgr::Bronze), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlightMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gold) : Sgr::of(Sgr::Bronze), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Brown) : Sgr::of(Sgr::Umber), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust), $this->unicode ? '❯' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust), $this->unicode ? '●' : '(*)') : ($this->unicode ? '○' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust), $this->unicode ? '█' : '|');
  }

}
