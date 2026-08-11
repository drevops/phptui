<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A field that presents a list of option rows.
 *
 * The rows are {@see Option} objects, so headings, separators and disabled
 * options share the list with the selectable ones; {@see OptionsCapableTrait}
 * carries the default implementation.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface OptionsCapableInterface {

  /**
   * The rows the field currently shows.
   *
   * @return list<\DrevOps\Tui\Block\Option>
   *   The visible rows.
   */
  public function visible(): array;

  /**
   * Render one option row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string;

}
