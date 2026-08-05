<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Theme\AbstractTheme;

/**
 * A theme asking for its width and options in the widest terms PHP allows.
 *
 * A parameter that accepts more than it is given still accepts what it is
 * given, so a theme written this way is one the factory can build.
 */
class WidenedWidthTheme extends AbstractTheme {

  /**
   * Construct a theme.
   *
   * @param float $width
   *   The columns available for the content it lays out.
   * @param iterable<string,mixed> $options
   *   Display options keyed by name.
   */
  public function __construct(float $width, iterable $options = []) {
    parent::__construct((int) $width, is_array($options) ? $options : iterator_to_array($options));
  }

}
