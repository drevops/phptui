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
 * {@see \DrevOps\Tui\Terminal\Terminal}.
 *
 * What it asks of a theme is the floor and nothing above it: a
 * {@see ThemeInterface} that can be built from a frame width and the options a
 * consumer stated, and that answers for every element a form is drawn from -
 * which is the constructor and the elements {@see AbstractTheme} carries.
 * Both are checked where a theme is picked, so a theme that cannot draw the
 * form is named there rather than partway through a frame. Everything else a
 * driver wants - a border, spacing, a wash, a patch - is a capability it asks
 * for and does without, so a theme that declares none is still a theme this
 * builds and a session runs.
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
   *   When the class is not a theme, or is one nothing can build or draw a
   *   form with - registration fails early rather than at the later create()
   *   call.
   */
  public static function register(string $name, string $class): void {
    self::$registry[$name] = self::vouch($class);
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
   *   When the name is neither registered nor a theme class name, or names a
   *   theme class nothing can build or draw a form with.
   */
  public static function create(string $name = 'default', int $width = ThemeInterface::DEFAULT_WIDTH, array $options = []): ThemeInterface {
    $name = $name === '' ? 'default' : $name;

    $class = self::$registry[$name] ?? NULL;

    if ($class === NULL) {
      if (!is_a($name, ThemeInterface::class, TRUE)) {
        throw new \InvalidArgumentException(sprintf('Unknown theme "%s". Use a registered name (%s), register one with ThemeManager::register(), or pass a theme class name.', $name, implode(', ', array_keys(self::$registry))));
      }

      $class = self::vouch($name);
    }

    return new $class($width, $options);
  }

  /**
   * Vouch for a theme class, or say why it is not one that can be built.
   *
   * @param string $class
   *   The class name.
   *
   * @return class-string<\DrevOps\Tui\Theme\ThemeInterface>
   *   The class.
   *
   * @throws \InvalidArgumentException
   *   When the class is not a theme, is one nothing can instantiate, cannot be
   *   built from a width and options, or cannot draw a form.
   */
  protected static function vouch(string $class): string {
    if (!is_a($class, ThemeInterface::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" must implement %s.', $class, ThemeInterface::class));
    }

    $reflection = new \ReflectionClass($class);

    // An abstract theme passes the type check and then fatals on the first
    // create(): refusing it here names the class rather than the call site.
    if (!$reflection->isInstantiable()) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" cannot be instantiated.', $class));
    }

    if (!self::builds($reflection->getConstructor())) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" must take a frame width and an options array.', $class));
    }

    $missing = array_diff(self::drawn(), class_implements($class) ?: []);

    if ($missing !== []) {
      throw new \InvalidArgumentException(sprintf('Theme class "%s" cannot draw a form: it does not implement %s.', $class, implode(', ', $missing)));
    }

    return $class;
  }

  /**
   * Whether a constructor takes the two arguments create() passes it.
   *
   * @param \ReflectionMethod|null $constructor
   *   The constructor, or NULL when the class declares none.
   *
   * @return bool
   *   TRUE when it takes a width and an options array.
   */
  protected static function builds(?\ReflectionMethod $constructor): bool {
    $parameters = $constructor instanceof \ReflectionMethod ? $constructor->getParameters() : [];

    if (count($parameters) < 2) {
      return FALSE;
    }

    // Nothing here can invent a third argument, so anything past the two it is
    // called with has to answer for itself.
    foreach (array_slice($parameters, 2) as $extra) {
      if (!$extra->isOptional()) {
        return FALSE;
      }
    }

    return self::takes($parameters[0], 'int') && self::takes($parameters[1], 'array');
  }

  /**
   * Whether a parameter accepts a value of the given type.
   *
   * @param \ReflectionParameter $parameter
   *   The parameter.
   * @param string $type
   *   The type name a caller hands it.
   *
   * @return bool
   *   TRUE when it accepts one.
   */
  protected static function takes(\ReflectionParameter $parameter, string $type): bool {
    $declared = $parameter->getType();

    // Only a single declared type can rule a value out: one that declares
    // nothing, or declares alternatives, still takes what it is handed.
    return !$declared instanceof \ReflectionNamedType || in_array($declared->getName(), [$type, 'mixed'], TRUE);
  }

  /**
   * The element interfaces a theme has to implement to draw a form.
   *
   * Read off the floor rather than written down here. The floor's promise is
   * that it answers for every element the blocks and the frame declare, so
   * reading the set off it means a block added to the library changes what a
   * theme has to draw by changing the one class that already has to draw it.
   *
   * @return array<string,class-string>
   *   The interfaces.
   */
  protected static function drawn(): array {
    return array_diff(class_implements(AbstractTheme::class) ?: [], [ThemeInterface::class]);
  }

}
