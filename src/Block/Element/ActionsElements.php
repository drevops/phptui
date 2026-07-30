<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the actions block composes.
 *
 * The framing around a label belongs here rather than in the block: a theme
 * that frames a button differently changes one method, and the block goes on
 * knowing only that it has labels and one of them has focus.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface ActionsElements {

  /**
   * Style a button that does not have focus.
   *
   * @param string $label
   *   The label.
   *
   * @return string
   *   The styled button.
   */
  public function actionButton(string $label): string;

  /**
   * Style the button that has focus.
   *
   * @param string $label
   *   The label.
   *
   * @return string
   *   The styled button.
   */
  public function actionSelected(string $label): string;

  /**
   * The gap standing between two buttons.
   *
   * @return string
   *   The styled separator.
   */
  public function actionSeparator(): string;

}
