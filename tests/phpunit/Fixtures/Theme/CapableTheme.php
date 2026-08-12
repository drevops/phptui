<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Theme\AbstractTheme;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableInterface;
use DrevOps\Tui\Theme\Capability\ColorSchemeCapableTrait;
use DrevOps\Tui\Theme\Capability\UnicodeCapableInterface;
use DrevOps\Tui\Theme\Capability\UnicodeCapableTrait;
use DrevOps\Tui\Theme\Sgr;

/**
 * Test fixture: a theme composing both support capabilities from their traits.
 *
 * It states the three flags and writes its elements in terms of the plumbing
 * the traits carry, which is the shape a consumer theme takes when it declares
 * a capability rather than reimplementing one.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Theme
 */
class CapableTheme extends AbstractTheme implements ColorSchemeCapableInterface, UnicodeCapableInterface {

  use ColorSchemeCapableTrait;
  use UnicodeCapableTrait;

  /**
   * Construct a theme over the terminal it is drawing into.
   *
   * @param bool $color
   *   Whether colour reaches the terminal.
   * @param bool $unicode
   *   Whether the terminal draws glyphs outside ASCII.
   * @param bool $is_dark
   *   Whether the terminal's background is dark.
   */
  public function __construct(bool $color = TRUE, bool $unicode = TRUE, bool $is_dark = TRUE) {
    $this->color = $color;
    $this->unicode = $unicode;
    $this->isDark = $is_dark;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbLabel(string $text): string {
    return $this->paint($this->isDark() ? Sgr::of(Sgr::Cyan) : Sgr::of(Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbSeparator(): string {
    return $this->glyph('›', '>');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldOption(string $text, bool $chosen, bool $focused = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Green), $chosen || $focused), $text);
  }

}
