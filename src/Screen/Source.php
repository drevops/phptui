<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * Where a field's initial value was resolved from.
 *
 * @package DrevOps\PhpTui\Screen
 */
enum Source {

  case Input;
  case Detected;
  case Default;

}
