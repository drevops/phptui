<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

use DrevOps\PhpTui\Block\Option;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * A field that presents a list of option rows.
 *
 * The rows are {@see Option} objects, so headings, separators and disabled
 * options share the list with the selectable ones; {@see OptionsCapableTrait}
 * carries the default implementation.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface OptionsCapableInterface {

  /**
   * The rows the field currently shows.
   *
   * @return list<\DrevOps\PhpTui\Block\Option>
   *   The visible rows.
   */
  public function visible(): array;

  /**
   * Render one option row.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\PhpTui\Block\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string;

}
