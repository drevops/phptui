<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The theme registry and factory.
 *
 * Themes are self-contained classes; this manager is how a theme name
 * becomes an instance. The built-in theme is pre-registered ("default"), a
 * consumer registers its own under a short name with {@see register()}, and
 * {@see create()} also accepts a fully-qualified theme class name directly, so
 * a one-off theme needs no registration at all. Terminal-capability detection
 * (colour, Unicode, dark/light background) lives on
 * {@see \DrevOps\Tui\Render\Terminal}.
 *
 * What it asks of a theme is the floor and nothing above it: a
 * {@see ThemeInterface} built from a frame width and the options a consumer
 * stated, which is the constructor {@see AbstractTheme} carries. Everything
 * else a driver wants - a border, spacing, a wash, a patch - is a capability
 * it asks for and does without, so a theme that declares none is still a theme
 * this builds and a session runs.
 *
 * @package DrevOps\Tui\Theme
 */
final class ThemeManager {

  /**
   * The name => theme-class registry.
   *
   * @var array<string,class-string<\DrevOps\Tui\Theme\ThemeInterface>>
   */
  protected static array $registry = [
    'default' => DefaultTheme::class,
    'midnight' => MidnightTheme::class,
    'frost' => FrostTheme::class,
    'ember' => EmberTheme::class,
    'mono' => MonoTheme::class,
    'dos' => DosTheme::class,
  ];

  /**
   * Register a theme class under a name so an appearance can select it.
   *
   * @param string $name
   *   The theme name.
   * @param string $class
   *   The theme class name.
   *
   * @throws \InvalidArgumentException
   *   When the class is not a theme - registration fails early rather than at
   *   the later create() call.
   */
  public static function register(string $name, string $class): void {
    if (!is_a($class, ThemeInterface::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" must implement %s.', $class, ThemeInterface::class));
    }

    self::$registry[$name] = $class;
  }

  /**
   * Create a theme by name.
   *
   * Lowest friction first: a fully-qualified theme class name is instantiated
   * directly, so a consumer can point at their own theme class with no
   * registration. Otherwise the name is looked up in the registry ("default" or
   * a name passed to {@see register()}). An unknown name fails loudly - a typo
   * should not silently render the default theme. Colour, Unicode and the
   * dark/light mode are display options carried in $options.
   *
   * @param string $name
   *   A registered name, a theme class name, or "" for the default theme.
   * @param int $width
   *   The frame width.
   * @param array<string,mixed> $options
   *   Display options passed to the theme (e.g. "mode", "color", "unicode",
   *   "spacing", "border").
   *
   * @return \DrevOps\Tui\Theme\ThemeInterface
   *   The theme instance.
   *
   * @throws \InvalidArgumentException
   *   When the name is neither registered nor a theme class name.
   */
  public static function create(string $name = 'default', int $width = DefaultTheme::DEFAULT_WIDTH, array $options = []): ThemeInterface {
    $name = $name === '' ? 'default' : $name;

    $class = self::$registry[$name] ?? (is_a($name, ThemeInterface::class, TRUE) ? $name : NULL);

    if ($class === NULL) {
      throw new \InvalidArgumentException(sprintf('Unknown theme "%s". Use a registered name (%s), register one with ThemeManager::register(), or pass a theme class name.', $name, implode(', ', array_keys(self::$registry))));
    }

    return new $class($width, $options);
  }

}
