<?php

declare(strict_types=1);

namespace Playground\Themes;

use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Sgr;

/**
 * The default theme plus one theme-invented option: a value "accent" colour.
 *
 * The whole recipe for a custom option: declare it in optionSchema() - so an
 * unknown key or a bad value still throws - and read it with option(). A
 * consumer sets it with the same plain-string options array as the built-ins
 * (`['accent' => 'warm']`); no enum, no separate options class, no import.
 */
class AccentTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function optionSchema(): array {
    return ['accent' => ['cool', 'warm', 'mono']] + parent::optionSchema();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return match ($this->option('accent', 'cool')) {
      'warm' => $this->paint($this->emphasize(Sgr::of(Sgr::Yellow), $emphatic), $text),
      'mono' => $this->paint($this->emphasize(Sgr::of(Sgr::Grey), $emphatic), $text),
      default => parent::value($text, $emphatic),
    };
  }

}
