<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * The vertical alignment of content within the available height.
 *
 * A consumer passes a case (or its string value) as the "valign" theme
 * option; it takes effect in fullscreen mode.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum VAlign: string {

  // Content sits against the top edge (the default).
  case Top = 'top';

  // Content centers within the available height.
  case Middle = 'middle';

  // Content sits against the bottom edge.
  case Bottom = 'bottom';

}
