<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Theme\AbstractTheme;

/**
 * Test fixture: a theme whose width has to be two interfaces at once.
 *
 * Nothing the factory has to offer can be two interfaces at once, so this is
 * the composite a width can never satisfy however it is handed over.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Theme
 */
class IntersectedWidthTheme extends AbstractTheme {

  /**
   * Construct a theme.
   *
   * @param \Countable&\Iterator $width
   *   Anything but the columns available for the content it lays out.
   * @param array<string,mixed> $options
   *   Display options keyed by name.
   */
  public function __construct(\Countable&\Iterator $width, array $options = []) {
    parent::__construct(count($width), $options);
  }

}
