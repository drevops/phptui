<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the panel block composes when it is nested.
 *
 * A panel draws a row of its own only as a sub-panel, which is the shape you
 * select to enter it. Once entered it draws nothing itself: its blocks do.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface PanelElementsInterface {

  /**
   * Style a nested panel's title.
   *
   * @param string $text
   *   The title.
   *
   * @return string
   *   The styled title.
   */
  public function panelTitle(string $text): string;

}
