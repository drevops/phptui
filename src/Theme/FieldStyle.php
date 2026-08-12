<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme;

/**
 * How a field's value input is drawn in the editor.
 *
 * A consumer passes a case (or its string value) as the "field" theme option;
 * when the option is unset, the flat style applies.
 *
 * @package DrevOps\PhpTui\Theme
 */
enum FieldStyle: string {

  // Plain styled text with a caret (the default).
  case Flat = 'flat';

  // The value on a fixed-width background fill, visible even when empty.
  case Boxed = 'boxed';

  // The value underlined across a fixed-width field.
  case Underline = 'underline';

}
