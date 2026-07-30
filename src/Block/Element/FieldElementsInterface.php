<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the field block composes.
 *
 * One field owns both of its modes, so one interface names both: the first
 * group is the single line a field draws until it is opened, and the second is
 * what it draws in the region it takes over once it is. A pair such as the
 * value and the draft, or the description and the entry description, is two
 * elements rather than one because each says a different thing, at a different
 * moment or about a different subject.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface FieldElementsInterface {

  /**
   * The mark saying which field has focus.
   *
   * @param bool $selected
   *   Whether this is the field the cursor is on.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function fieldSelector(bool $selected): string;

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
   * The mark saying a field has help to show.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldHelpMarker(): string;

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
   * What stands between the parts of an answer that has more than one.
   *
   * @return string
   *   The styled separator.
   */
  public function fieldValueSeparator(): string;

  /**
   * Style the mark saying where an answer came from.
   *
   * @param string $text
   *   The provenance, as it reads.
   *
   * @return string
   *   The styled badge.
   */
  public function fieldBadge(string $text): string;

  /**
   * Style a field's own explanatory text.
   *
   * @param string $text
   *   The description.
   *
   * @return string
   *   The styled description.
   */
  public function fieldDescription(string $text): string;

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
   * The mark saying which entry has focus.
   *
   * Its own element rather than a reuse of the field selector: one says which
   * field you are on and the other which entry within it, so a theme can
   * restyle either without touching the other.
   *
   * @param bool $selected
   *   Whether this is the entry the cursor is on.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function fieldEntrySelector(bool $selected): string;

  /**
   * The mark recording that an entry was picked.
   *
   * Selecting and marking are different things: a selector says where you are,
   * and this says what you decided.
   *
   * @param bool $chosen
   *   Whether the entry is picked.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldEntryMarker(bool $chosen): string;

  /**
   * Style a qualifier on an entry, such as why it is unavailable.
   *
   * @param string $text
   *   The note.
   *
   * @return string
   *   The styled note.
   */
  public function fieldEntryNote(string $text): string;

  /**
   * Style the focused entry's own explanatory text.
   *
   * @param string $text
   *   The description.
   *
   * @return string
   *   The styled description.
   */
  public function fieldEntryDescription(string $text): string;

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

  /**
   * The mark showing where the next keystroke lands.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldCaret(): string;

  /**
   * Style what is being typed, before it is accepted.
   *
   * @param string $text
   *   The draft.
   *
   * @return string
   *   The styled draft.
   */
  public function fieldDraft(string $text): string;

  /**
   * Style what the field is doing right now.
   *
   * @param string $text
   *   The state.
   *
   * @return string
   *   The styled state.
   */
  public function fieldState(string $text): string;

  /**
   * Style the line saying what the list below is showing.
   *
   * @param string $text
   *   The caption.
   *
   * @return string
   *   The styled caption.
   */
  public function fieldCaption(string $text): string;

}
