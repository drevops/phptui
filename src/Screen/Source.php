<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * Where a field's initial value was resolved from.
 *
 * @package DrevOps\Tui\Screen
 */
enum Source {

  case Input;
  case Detected;
  case Default;

}
