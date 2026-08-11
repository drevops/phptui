<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen\Layout;

use AlexSkrypnyk\Str2Name\Str2Name;

/**
 * The layouts a form can pick from, by name.
 *
 * Reuse is what a layout has that a region does not: it is named, and the same
 * one serves a screen and a panel. The ones that ship are the layout classes
 * beside this one, read from the directory rather than listed here, so a list
 * cannot fall out of step with what is actually shipped.
 *
 * Three ways reach a layout - a shipped name, a name a consumer registered, or
 * the class itself - and each call builds its own, because two forms picking
 * the same layout must not share its regions. All three hand over a name and
 * nothing else, so an arrangement built from anything a name cannot carry is
 * reached none of these ways: it is built where that something is written.
 *
 * @package DrevOps\Tui\Screen\Layout
 */
final class LayoutManager {

  /**
   * The suffix a layout class name carries.
   */
  protected const string SUFFIX = 'Layout';

  /**
   * The shipped layouts, keyed by name, once the directory has been read.
   *
   * @var array<string,class-string<\DrevOps\Tui\Screen\Layout\LayoutInterface>>|null
   */
  protected static ?array $shipped = NULL;

  /**
   * The layouts a consumer registered, keyed by name.
   *
   * @var array<string,class-string<\DrevOps\Tui\Screen\Layout\LayoutInterface>>
   */
  protected static array $registry = [];

  /**
   * Register a layout under a short name.
   *
   * @param string $name
   *   The name a form picks it by.
   * @param string $class
   *   The layout class.
   */
  public static function register(string $name, string $class): void {
    self::$registry[$name] = self::vouch($class);
  }

  /**
   * Build a layout by name, or by class name.
   *
   * @param string $name
   *   A shipped name, a registered name, or a layout class name.
   *
   * @return \DrevOps\Tui\Screen\Layout\LayoutInterface
   *   The layout.
   */
  public static function create(string $name = 'default'): LayoutInterface {
    $class = self::shipped()[$name] ?? self::$registry[$name] ?? NULL;

    if ($class !== NULL) {
      return new $class();
    }

    if (!is_a($name, LayoutInterface::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unknown layout "%s". Registered: %s.', $name, implode(', ', self::names())));
    }

    $class = self::vouch($name);

    return new $class();
  }

  /**
   * The names a layout can be reached by, the shipped ones first.
   *
   * @return list<string>
   *   The names.
   */
  public static function names(): array {
    return array_keys(self::shipped() + self::$registry);
  }

  /**
   * Forget every registration a consumer made.
   */
  public static function reset(): void {
    self::$registry = [];
  }

  /**
   * The layouts that ship, keyed by the name a form picks them by.
   *
   * @return array<string,class-string<\DrevOps\Tui\Screen\Layout\LayoutInterface>>
   *   The classes, keyed by name.
   */
  protected static function shipped(): array {
    if (self::$shipped !== NULL) {
      return self::$shipped;
    }

    $shipped = [];

    foreach (glob(__DIR__ . '/*.php') ?: [] as $file) {
      $short = basename($file, '.php');
      $class = __NAMESPACE__ . '\\' . $short;

      // The directory holds this manager and the interface too.
      if (!is_a($class, LayoutInterface::class, TRUE)) {
        continue;
      }

      // An arrangement nobody can build from its name alone is not one a form
      // can pick by name.
      if (!self::isNameable($class)) {
        continue;
      }

      $shipped[self::name($short)] = $class;
    }

    return self::$shipped = $shipped;
  }

  /**
   * The name a shipped class is picked by.
   *
   * @param string $short
   *   The class name, without its namespace.
   *
   * @return string
   *   The name.
   */
  protected static function name(string $short): string {
    // The suffix is what makes the class name read as a layout; the name a form
    // picks it by does not need saying twice.
    if (str_ends_with($short, self::SUFFIX)) {
      $short = substr($short, 0, -strlen(self::SUFFIX));
    }

    return Str2Name::pascal2kebab($short);
  }

  /**
   * Vouch for a layout class, or say why it is not one.
   *
   * @param string $class
   *   The class name.
   *
   * @return class-string<\DrevOps\Tui\Screen\Layout\LayoutInterface>
   *   The class.
   */
  protected static function vouch(string $class): string {
    if (!is_a($class, LayoutInterface::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Layout class "%s" must implement %s.', $class, LayoutInterface::class));
    }

    // An abstract layout, or one built from something a name cannot carry,
    // passes the type check and then fatals on the first create(): refusing it
    // here names the class rather than the call site.
    if (!self::isNameable($class)) {
      throw new \InvalidArgumentException(sprintf('Layout class "%s" cannot be built from a name alone.', $class));
    }

    return $class;
  }

  /**
   * Whether a layout class is one a name is enough to build.
   *
   * All three routes hand over a name and nothing else, so an arrangement they
   * can build is one that asks for nothing else either. A grid is the standing
   * example of what that rules out: its shape is what it arranges, two grids of
   * different shapes are different arrangements, and no name says which.
   *
   * @param class-string $class
   *   The class name.
   *
   * @return bool
   *   TRUE when it can.
   */
  protected static function isNameable(string $class): bool {
    $reflection = new \ReflectionClass($class);

    return $reflection->isInstantiable() && ($reflection->getConstructor()?->getNumberOfParameters() ?? 0) === 0;
  }

}
