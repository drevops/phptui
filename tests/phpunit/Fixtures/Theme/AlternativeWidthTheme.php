<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Theme\AbstractTheme;

/**
 * Test fixture: a theme whose width may be either of two things, neither one.
 *
 * A parameter declaring alternatives still declares what it will not take, so
 * this is a theme the factory would hand a width to and be refused by.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class AlternativeWidthTheme extends AbstractTheme {

  /**
   * Construct a theme.
   *
   * @param string|bool $width
   *   Anything but the columns available for the content it lays out.
   * @param array<string,mixed> $options
   *   Display options keyed by name.
   */
  public function __construct(string|bool $width, array $options = []) {
    parent::__construct(is_string($width) ? (int) $width : 0, $options);
  }

}
