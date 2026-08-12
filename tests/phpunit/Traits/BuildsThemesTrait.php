<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Traits;

use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\DefaultTheme;
use DrevOps\PhpTui\Theme\Mode;
use DrevOps\PhpTui\Theme\Spacing;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Theme\ThemeManager;

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
   * @return \DrevOps\PhpTui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(bool $color = TRUE, bool $unicode = TRUE): DefaultTheme {
    return $this->builtin('default', ThemeInterface::DEFAULT_WIDTH, ['color' => $color, 'unicode' => $unicode, 'mode' => Mode::Dark]);
  }

  /**
   * A shipped theme, resolved through the registry by the name it ships under.
   *
   * The factory answers with the surface every theme has rather than the class
   * the shipped ones happen to share, so a test reading a palette back off one
   * says which theme it is holding before it reads.
   *
   * @param string $name
   *   The theme name.
   * @param int $width
   *   The frame width.
   * @param array<string,mixed> $options
   *   The display options.
   *
   * @return \DrevOps\PhpTui\Theme\DefaultTheme
   *   The theme.
   */
  protected function builtin(string $name, int $width = ThemeInterface::DEFAULT_WIDTH, array $options = []): DefaultTheme {
    $theme = ThemeManager::create($name, $width, $options);

    $this->assertInstanceOf(DefaultTheme::class, $theme);

    return $theme;
  }

  /**
   * A narrow, unstyled, borderless theme for frame and layout assertions.
   *
   * Independent of the theme's bordered-and-padded defaults, so an assertion
   * reads against the content alone.
   *
   * @return \DrevOps\PhpTui\Theme\DefaultTheme
   *   The theme.
   */
  protected function plainTheme(): DefaultTheme {
    return new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]);
  }

}
