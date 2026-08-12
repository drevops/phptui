<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Element;

/**
 * The elements the actions block composes.
 *
 * The framing around a label belongs here rather than in the block: a theme
 * that frames a button differently changes one method, and the block goes on
 * knowing only that it has labels and one of them would be pressed.
 *
 * Where the cursor is and which button it would press are two questions, so
 * they are two elements. The mark answers the first the way every other row a
 * cursor lands on answers it, which is also why it is a glyph: the second is a
 * colour treatment, and a terminal drawing no colour would leave a row that
 * said only that with nothing to say at all.
 *
 * Withholding the submit reads as one thing with the buttons it withholds, so
 * the reason is an element here rather than a note somebody else draws above
 * them: nothing can come between a refusal and what it refuses.
 *
 * @package DrevOps\PhpTui\Block\Element
 */
interface ActionsElementsInterface {

  /**
   * The mark saying the buttons have focus.
   *
   * @param bool $selected
   *   Whether the cursor is on them.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function actionSelector(bool $selected): string;

  /**
   * Style a button that would not be pressed.
   *
   * @param string $label
   *   The label.
   *
   * @return string
   *   The styled button.
   */
  public function actionButton(string $label): string;

  /**
   * Style the button that would be pressed.
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

  /**
   * Style the reason the form cannot be ended yet.
   *
   * @param string $reason
   *   The reason.
   *
   * @return string
   *   The styled reason.
   */
  public function actionRefusal(string $reason): string;

}
