<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * The vertical spacing of the rendered panel body.
 *
 * A consumer passes a case (or its string value) as the "spacing" theme
 * option.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum Spacing: string {

  // Labels and values only, no descriptions, no gaps.
  case Compact = 'compact';

  // Descriptions under each item, no gaps.
  case Normal = 'normal';

  // Descriptions plus a blank line between items.
  case Padded = 'padded';

}
