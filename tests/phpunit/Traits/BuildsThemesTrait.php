<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Traits;

use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\ThemeManager;

/**
 * Provides the two themes rendering assertions are made against.
 */
trait BuildsThemesTrait {

  /**
   * The default theme at its own width, pinned to dark.
   *
   * @param bool $color
   *   Whether colour is on.
   * @param bool $unicode
   *   Whether Unicode glyphs are on.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(bool $color = TRUE, bool $unicode = TRUE): DefaultTheme {
    return ThemeManager::create('default', DefaultTheme::DEFAULT_WIDTH, ['color' => $color, 'unicode' => $unicode, 'mode' => Mode::Dark]);
  }

  /**
   * A narrow, unstyled, borderless theme for frame and layout assertions.
   *
   * Independent of the theme's bordered-and-padded defaults, so an assertion
   * reads against the content alone.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function plainTheme(): DefaultTheme {
    return new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
  }

}
