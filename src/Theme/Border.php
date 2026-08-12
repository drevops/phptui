<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * The frame border style.
 *
 * A consumer passes a case (or its string value) as the "border" theme
 * option.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum Border: string {

  // No box.
  case None = 'none';

  // A single-line box.
  case Line = 'line';

  // A single-line box with rounded corners.
  case Rounded = 'rounded';

  // A double-line box.
  case Double = 'double';

}
