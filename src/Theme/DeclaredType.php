<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme;

/**
 * A type a constructor parameter declares, as PHP names it.
 *
 * Only the ones a frame width or an options array can be handed to are here:
 * the two themselves, the two PHP accepts them as, and the one that rules
 * nothing out. Anything else a parameter declares takes neither.
 *
 * @package DrevOps\Tui\Theme
 */
enum DeclaredType: string {

  // A frame width as it is measured.
  case Int = 'int';

  // What an integer widens to, which strict typing allows on the way in.
  case Float = 'float';

  // The display options, keyed by name.
  case Array = 'array';

  // What an array is one of, so a parameter asking for either takes one.
  case Iterable = 'iterable';

  // A parameter that refuses nothing.
  case Mixed = 'mixed';

}
