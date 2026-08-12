<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Theme\AbstractTheme;

/**
 * Test fixture: a theme asking for an argument nothing can supply.
 *
 * It takes the width and the options it is offered and then one more thing
 * besides, so it is a theme every type check accepts and the factory has no
 * way to build.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class UnbuildableTheme extends AbstractTheme {

  /**
   * Construct a theme.
   *
   * @param int $width
   *   The columns available for the content it lays out.
   * @param array<string,mixed> $options
   *   Display options keyed by name.
   * @param string $palette
   *   The palette it paints from.
   */
  public function __construct(int $width, array $options, protected string $palette) {
    parent::__construct($width, $options);
  }

}
