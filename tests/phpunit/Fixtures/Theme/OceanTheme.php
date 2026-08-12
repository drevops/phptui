<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Theme;

use DrevOps\PhpTui\Theme\DefaultTheme;

/**
 * Test fixture: a custom theme selectable by class through ThemeManager.
 *
 * Repaints one hue so it is visibly distinct from the default, while inheriting
 * the (width, options) constructor so it works with the standard theme factory.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Theme
 */
class OceanTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return '1;96';
  }

}
