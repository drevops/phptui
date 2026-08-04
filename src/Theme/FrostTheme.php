<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A calm, arctic theme: frost-blue accents, sage values, sand highlights.
 *
 * A curated 256-colour palette selectable by name ("frost"). It states its
 * colours and inherits every element from the default theme, so it renders
 * across every field and degrades to plain text when colour is off.
 *
 * @package DrevOps\Tui\Theme
 */
class FrostTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return $this->isDark ? Sgr::of(Sgr::Bold, Sgr::Sky) : Sgr::of(Sgr::Bold, Sgr::Cobalt);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize($this->isDark ? Sgr::of(Sgr::Sage) : Sgr::of(Sgr::Moss), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Sand) : Sgr::of(Sgr::Ochre), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function border(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Steel) : Sgr::of(Sgr::Teal), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldEntryMatch(string $text): string {
    return $this->paint($this->isDark ? Sgr::of(Sgr::Sand) : Sgr::of(Sgr::Ochre), $text);
  }

}
