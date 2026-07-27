<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

/**
 * The kinds of inline span a {@see Markup} source resolves to.
 *
 * @package DrevOps\Tui\Render
 */
enum MarkupKind {

  case Text;
  case Bold;
  case Emphasis;
  case Code;
  case Link;

}
