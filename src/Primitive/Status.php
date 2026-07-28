<?php

declare(strict_types=1);

namespace DrevOps\Tui\Primitive;

/**
 * The kind of a status line: what the message says about the run.
 *
 * The case picks both the glyph and the colour the theme draws the line in, so
 * the five stay distinguishable with colour off and in ASCII alike.
 *
 * @package DrevOps\Tui\Primitive
 */
enum Status: string {

  // An aside, quieter than the output around it.
  case Note = 'note';

  // A neutral statement of what is happening.
  case Info = 'info';

  // Something finished as intended.
  case Success = 'success';

  // Something needs attention but did not stop the run.
  case Warning = 'warning';

  // Something failed.
  case Error = 'error';

}
