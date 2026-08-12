<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Derive;

use AlexSkrypnyk\Str2Name\Str2Name;

/**
 * Value transforms for derive rules.
 *
 * Exposes an allowlisted subset of str2name conversions (machine, kebab,
 * pascal, host, initials, ...) as derive transforms. {@see Derive} validates
 * a declared name against supports() at declaration time.
 *
 * @package DrevOps\PhpTui\Derive
 */
class Transform extends Str2Name {

  /**
   * The str2name conversions exposed as transforms.
   */
  protected const STR2NAME = [
    'machine',
    'kebab',
    'pascal',
    'snake',
    'camel',
    'cobol',
    'train',
    'flat',
    'constant',
    'label',
    'sentence',
    'abbreviation',
    'domain',
    'filepath',
    'id',
    'phpClass',
    'phpFunction',
    'phpMethod',
    'phpNamespace',
    'phpPackage',
    'phpPackageName',
    'host',
    'lower',
    'upper',
    'initials',
  ];

  /**
   * Apply a named transform to a value.
   *
   * @param string $value
   *   The value.
   * @param string $name
   *   The transform name.
   *
   * @return string
   *   The transformed value, or the value unchanged for an unknown name.
   */
  public static function apply(string $value, string $name): string {
    $callable = [static::class, $name];

    if (!static::supports($name) || !is_callable($callable)) {
      return $value;
    }

    $result = $callable($value);

    return is_string($result) ? $result : $value;
  }

  /**
   * Whether a transform name is supported.
   *
   * @param string $name
   *   The transform name.
   *
   * @return bool
   *   TRUE when the name maps to a known transform.
   */
  public static function supports(string $name): bool {
    return in_array($name, static::names(), TRUE);
  }

  /**
   * The supported transform names.
   *
   * @return list<string>
   *   The allowlisted str2name conversion names.
   */
  public static function names(): array {
    return array_values(static::STR2NAME);
  }

}
