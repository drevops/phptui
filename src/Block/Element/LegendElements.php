<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the legend block composes.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface LegendElements {

  /**
   * Style one key.
   *
   * @param string $text
   *   The key as it is written.
   *
   * @return string
   *   The styled key.
   */
  public function legendKey(string $text): string;

  /**
   * Style what a key does.
   *
   * @param string $text
   *   The action.
   *
   * @return string
   *   The styled description.
   */
  public function legendDescription(string $text): string;

  /**
   * The mark standing between two entries.
   *
   * @return string
   *   The styled separator.
   */
  public function legendSeparator(): string;

}
