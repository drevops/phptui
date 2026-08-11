<?php

declare(strict_types=1);

namespace DrevOps\Tui\Theme\Capability;

use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\Sgr;

/**
 * Painting behaviour: the palette plumbing every painted element builds on.
 *
 * A theme that composes this states the two flags and inherits the rest, so a
 * palette is written as colour choices rather than as escape-sequence
 * handling.
 *
 * @package DrevOps\Tui\Theme\Capability
 */
trait ColorSchemeCapableTrait {

  /**
   * Whether colour reaches the terminal.
   */
  protected bool $color = TRUE;

  /**
   * Whether the terminal's background is dark.
   */
  protected bool $isDark = TRUE;

  /**
   * {@inheritdoc}
   */
  public function isColor(): bool {
    return $this->color;
  }

  /**
   * {@inheritdoc}
   */
  public function isDark(): bool {
    return $this->isDark;
  }

  /**
   * Wrap text in an SGR code, honouring colour-off.
   *
   * The single low-level helper every styler builds on.
   *
   * @param string $sgr
   *   The SGR parameters (e.g. "1;36"); empty leaves the text unstyled.
   * @param string $text
   *   The text.
   *
   * @return string
   *   The styled text (unchanged when colour is off).
   */
  protected function paint(string $sgr, string $text): string {
    return Ansi::style($text, $this->color ? $sgr : '');
  }

  /**
   * Add bold to an SGR code when an item is selected.
   *
   * @param string $sgr
   *   The base SGR code.
   * @param bool $selected
   *   Whether the item is the selected (cursor) one.
   *
   * @return string
   *   The code, made bold when selected.
   */
  protected function emphasize(string $sgr, bool $selected): string {
    if (!$selected) {
      return $sgr;
    }

    $drop = ['', Sgr::Bold->value, Sgr::Dim->value];
    $parts = array_values(array_filter(explode(';', $sgr), static fn(string $part): bool => !in_array($part, $drop, TRUE)));
    array_unshift($parts, Sgr::Bold->value);

    return implode(';', $parts);
  }

}
