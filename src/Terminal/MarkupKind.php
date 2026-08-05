<?php

declare(strict_types=1);

namespace DrevOps\Tui\Terminal;

/**
 * The kinds of inline span a {@see Markup} source resolves to.
 *
 * @package DrevOps\Tui\Terminal
 */
enum MarkupKind {

  case Text;
  case Bold;
  case Emphasis;
  case Code;
  case Link;

}
