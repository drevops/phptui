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
   * The mark saying which row has focus.
   *
   * @param bool $selected
   *   Whether this is the row the cursor is on.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function panelSelector(bool $selected): string;

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

  /**
   * The mark saying the row leads somewhere rather than opening in place.
   *
   * @return string
   *   The styled mark.
   */
  public function panelDescend(): string;

  /**
   * Style a nested panel's standing text.
   *
   * @param string $text
   *   The description.
   *
   * @return string
   *   The styled description.
   */
  public function panelDescription(string $text): string;

  /**
   * Style the run of answers a nested panel is holding.
   *
   * @param string $text
   *   The summary.
   *
   * @return string
   *   The styled summary.
   */
  public function panelSummary(string $text): string;

  /**
   * The mark standing between two answers in that run.
   *
   * @return string
   *   The styled separator.
   */
  public function panelSummarySeparator(): string;

}
