<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Fixtures\Theme;

use DrevOps\Tui\Theme\AbstractTheme;

/**
 * Test fixture: a theme on the floor that reads an option of its own.
 *
 * The other half of what a theme is built from - the frame width and what a
 * consumer stated - shown on a theme that declares no capability at all, so
 * the options reaching it cannot have come from anything above the floor.
 *
 * @package DrevOps\Tui\Tests\Fixtures\Theme
 */
class FloorOptionTheme extends AbstractTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldLabel(string $text): string {
    $lead = $this->options['lead'] ?? '';

    return is_string($lead) ? $lead . $text : $text;
  }

}
