<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * The layouts a form can pick from, by name.
 *
 * Reuse is what a layout has that a region does not: it is named, and the same
 * one serves a screen and a panel. Two ship, because two cover the two axes -
 * anything else is those two nested, or a layout a consumer writes and
 * registers here.
 *
 * @package DrevOps\Tui\Screen
 */
final class LayoutManager {

  /**
   * The layouts that ship.
   */
  protected const array SHIPPED = [
    'default' => DefaultLayout::class,
    'two-column' => TwoColumnLayout::class,
  ];

  /**
   * The registered layouts, keyed by name.
   *
   * @var array<string,class-string<\DrevOps\Tui\Screen\AbstractLayout>>
   */
  protected static array $registry = self::SHIPPED;

  /**
   * Register a layout under a short name.
   *
   * @param string $name
   *   The name a form picks it by.
   * @param string $class
   *   The layout class.
   */
  public static function register(string $name, string $class): void {
    if (!is_a($class, AbstractLayout::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Layout class "%s" must extend %s.', $class, AbstractLayout::class));
    }

    // An abstract subclass passes the type check and then fatals on the first
    // create(): refusing it here names the class rather than the call site.
    if (!(new \ReflectionClass($class))->isInstantiable()) {
      throw new \InvalidArgumentException(sprintf('Layout class "%s" cannot be instantiated.', $class));
    }

    self::$registry[$name] = $class;
  }

  /**
   * Build a layout by name, or by class name.
   *
   * Each call builds its own, because two forms picking the same layout must
   * not share its regions - one would see the other's blocks.
   *
   * @param string $name
   *   A registered name, or a layout class name.
   *
   * @return \DrevOps\Tui\Screen\AbstractLayout
   *   The layout.
   */
  public static function create(string $name = 'default'): AbstractLayout {
    $class = self::$registry[$name] ?? (is_a($name, AbstractLayout::class, TRUE) ? $name : NULL);

    if ($class === NULL) {
      throw new \InvalidArgumentException(sprintf('Unknown layout "%s". Registered: %s.', $name, implode(', ', array_keys(self::$registry))));
    }

    return new $class();
  }

  /**
   * Forget every registration a consumer made.
   */
  public static function reset(): void {
    self::$registry = self::SHIPPED;
  }

}
