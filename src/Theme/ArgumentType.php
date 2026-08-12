<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * What a theme's constructor is handed.
 *
 * The factory has a frame width and an options array to offer and nothing
 * else, so what a constructor may ask for is a closed set of two - and each of
 * them is satisfied by more than one declared type, since a parameter widening
 * what it accepts still accepts what it is given.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum ArgumentType {

  // The frame width the theme lays its rows out to.
  case Width;

  // The display options, keyed by name.
  case Options;

  /**
   * The types a parameter can declare and still take this argument.
   *
   * @return list<\DrevOps\PhpTui\Theme\DeclaredType>
   *   The types.
   */
  public function accepted(): array {
    return match ($this) {
      self::Width => [DeclaredType::Int, DeclaredType::Float, DeclaredType::Mixed],
      self::Options => [DeclaredType::Array, DeclaredType::Iterable, DeclaredType::Mixed],
    };
  }

}
