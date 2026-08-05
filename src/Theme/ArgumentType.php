<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * The types a theme's constructor is handed, as PHP names them.
 *
 * The factory has a frame width and an options array to offer and nothing
 * else, so what a constructor may declare for either of them is a closed set:
 * the two themselves, and the one type that rules nothing out.
 *
 * @package DrevOps\Tui\Theme
 */
enum ArgumentType: string {

  // The frame width.
  case Width = 'int';

  // The display options, keyed by name.
  case Options = 'array';

  // A parameter that refuses nothing, so it takes either of them.
  case Anything = 'mixed';

}
