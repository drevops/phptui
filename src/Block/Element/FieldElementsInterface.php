<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the field block composes.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface FieldElementsInterface {

  /**
   * Style a field's name.
   *
   * @param string $text
   *   The label.
   *
   * @return string
   *   The styled label.
   */
  public function fieldLabel(string $text): string;

  /**
   * Style the settled answer.
   *
   * @param string $text
   *   The value as it reads.
   *
   * @return string
   *   The styled value.
   */
  public function fieldValue(string $text): string;

  /**
   * The mark saying a field has help to show.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldHelpMarker(): string;

  /**
   * Style one entry of a list a field opens onto.
   *
   * @param string $text
   *   The entry label.
   * @param bool $chosen
   *   Whether it is chosen.
   *
   * @return string
   *   The styled entry.
   */
  public function fieldEntry(string $text, bool $chosen): string;

  /**
   * Style what a field will accept, before anything is refused.
   *
   * @param string $text
   *   The constraint.
   *
   * @return string
   *   The styled constraint.
   */
  public function fieldConstraint(string $text): string;

  /**
   * Style why a value was refused.
   *
   * @param string $text
   *   The message.
   *
   * @return string
   *   The styled error.
   */
  public function fieldError(string $text): string;

}
