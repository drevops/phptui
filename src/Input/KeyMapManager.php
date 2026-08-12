<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Input;

/**
 * The key-map preset registry and factory.
 *
 * Presets are self-contained classes; this manager is how a preset
 * name becomes a resolved {@see KeyMap}. The built-ins are pre-registered
 * ("default" and "vim"), a consumer registers its own under a short name with
 * {@see register()}, and {@see create()} also accepts a fully-qualified preset
 * class name directly, so a one-off preset needs no registration. Overrides
 * passed to {@see create()} are appended after the preset's own bindings, so a
 * consumer can retune individual bindings without replacing the whole preset.
 *
 * @package DrevOps\PhpTui\Input
 */
final class KeyMapManager {

  /**
   * The name => preset-class registry.
   *
   * @var array<string,class-string<\DrevOps\PhpTui\Input\DefaultKeyMap>>
   */
  protected static array $registry = [
    'default' => DefaultKeyMap::class,
    'vim' => VimKeyMap::class,
  ];

  /**
   * Register a preset class under a name so an appearance can select it.
   *
   * @param string $name
   *   The preset name.
   * @param string $class
   *   The preset class name.
   *
   * @throws \InvalidArgumentException
   *   When the class is not a DefaultKeyMap (or subclass) - registration fails
   *   early rather than at the later create() call.
   */
  public static function register(string $name, string $class): void {
    if (!is_a($class, DefaultKeyMap::class, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Key-map preset class "%s" must extend %s.', $class, DefaultKeyMap::class));
    }

    self::$registry[$name] = $class;
  }

  /**
   * Create a resolved key map from a preset and optional overrides.
   *
   * Lowest friction first: a fully-qualified preset class name is instantiated
   * directly, so a consumer can point at their own preset class with no
   * registration. Otherwise the name is looked up in the registry ("default",
   * "vim" or a name passed to {@see register()}). An unknown name fails
   * loudly - a typo should not silently fall back to the defaults.
   *
   * @param string $name
   *   A registered name, a preset class name, or "" for the default preset.
   * @param list<\DrevOps\PhpTui\Input\Binding> $overrides
   *   Bindings appended after the preset's own, retuning individual bindings.
   *
   * @return \DrevOps\PhpTui\Input\KeyMap
   *   The resolved, validated key map.
   *
   * @throws \InvalidArgumentException
   *   When the name is neither registered nor a preset class name, or a binding
   *   conflicts or is malformed.
   */
  public static function create(string $name = 'default', array $overrides = []): KeyMap {
    $name = $name === '' ? 'default' : $name;

    $class = self::$registry[$name] ?? (is_a($name, DefaultKeyMap::class, TRUE) ? $name : NULL);

    if ($class === NULL) {
      throw new \InvalidArgumentException(sprintf('Unknown key-map preset "%s". Use a registered name (%s), register one with KeyMapManager::register(), or pass a DefaultKeyMap subclass name.', $name, implode(', ', array_keys(self::$registry))));
    }

    return new KeyMap(array_merge((new $class())->bindings(), $overrides));
  }

}
