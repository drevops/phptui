<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A warm, retro theme: burnt-orange accents, olive values, gold highlights.
 *
 * A curated 256-colour palette selectable by name ("ember"). It states its
 * colours and inherits every element from the default theme, so it renders
 * across every field and degrades to plain text when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class EmberTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return $this->isDark ? Sgr::of(Sgr::Bold, Sgr::Orange) : Sgr::of(Sgr::Bold, Sgr::Rust);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Olive) : Sgr::of(Sgr::Khaki), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gold) : Sgr::of(Sgr::Bronze), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Brown) : Sgr::of(Sgr::Umber), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntryMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Gold) : Sgr::of(Sgr::Bronze), $text);
  }

}
