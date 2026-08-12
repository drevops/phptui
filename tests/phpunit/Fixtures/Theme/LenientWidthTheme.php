<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Theme\AbstractTheme;

/**
 * Test fixture: a theme taking a width among the things it will accept.
 *
 * A parameter declaring alternatives takes the value when any one of them
 * does, so a theme this lenient is one the factory can still build.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class LenientWidthTheme extends AbstractTheme {

  /**
   * Construct a theme.
   *
   * @param int|string $width
   *   The columns available for the content it lays out, however stated.
   * @param array<string,mixed> $options
   *   Display options keyed by name.
   */
  public function __construct(int|string $width, array $options = []) {
    parent::__construct((int) $width, $options);
  }

}
