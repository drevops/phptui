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
 * A few elements answer with a whole composed line rather than one styled
 * string - the input line, the scale, the marker pairs. Each still takes plain
 * scalars and nothing else, so the piece is the theme's to arrange without the
 * field having to hand over any of its state.
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
   * The blank gutter a field's rows are laid out after.
   *
   * A question asked only once an earlier one is answered steps in from the
   * questions that decide it, so a panel reads as a hierarchy rather than as a
   * flat list. Every row the field contributes is laid out after this same
   * gutter, and a theme that would rather draw a flat list answers with none.
   *
   * @param int $depth
   *   How many answers had to be given before this one is asked at all; zero
   *   for a question that is always asked.
   *
   * @return string
   *   The gutter, empty when nothing steps in.
   */
  public function fieldIndent(int $depth): string;

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
   * The character standing in for one character of a secret.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldMask(): string;

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
   * Being picked and being where the cursor rests are two different facts about
   * an entry, and an entry can be either, both or neither: a settled row draws
   * what was picked with no cursor anywhere in it, and a cursor moving down an
   * open list picks nothing.
   *
   * @param string $text
   *   The entry label.
   * @param bool $chosen
   *   Whether it is picked.
   * @param bool $focused
   *   Whether the cursor rests on it.
   *
   * @return string
   *   The styled entry.
   */
  public function fieldEntry(string $text, bool $chosen, bool $focused = FALSE): string;

  /**
   * Style the run of an entry's label that answers what was typed.
   *
   * @param string $text
   *   The matched run.
   *
   * @return string
   *   The styled run.
   */
  public function fieldEntryMatch(string $text): string;

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
   * and this says what you decided. Whether picking one entry unpicks the last
   * is a fact about the question rather than about the mark, so the mark is
   * told - a question that takes one answer and a question that takes several
   * are worth telling apart before anything has been picked at all.
   *
   * @param bool $chosen
   *   Whether the entry is picked.
   * @param bool $exclusive
   *   Whether picking this entry gives up every other, rather than adding to
   *   what is already picked.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldEntryMarker(bool $chosen, bool $exclusive = FALSE): string;

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
   * The mark standing between two runs of entries.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldEntrySeparator(): string;

  /**
   * Style what a field will accept, before anything is refused.
   *
   * The guidance voice, and the one line that has to survive a surface with
   * nothing to spend: it sits directly under an entry's own explanatory text,
   * so with no colour and no dependable italic it needs a mark of its own or a
   * reader cannot tell an expectation from prose.
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
   * Style the completion offered after the draft, which was not typed.
   *
   * @param string $text
   *   The suffix.
   *
   * @return string
   *   The styled suffix, empty where nothing can tell it from typed text.
   */
  public function fieldGhost(string $text): string;

  /**
   * The line an answer is typed on: the draft, the caret and the completion.
   *
   * The three are one piece because where the caret sits is a position within
   * the draft rather than a thing beside it, so only whatever draws the draft
   * can put it there.
   *
   * @param string $before
   *   The draft before the caret.
   * @param string $after
   *   The draft after it.
   * @param string $ghost
   *   The completion offered after the draft, or an empty string for none.
   *
   * @return string
   *   The composed line.
   */
  public function fieldInput(string $before, string $after, string $ghost = ''): string;

  /**
   * The run of points a graded answer reads as.
   *
   * One element behind both the open grade and the settled row, so what a row
   * says and what opening it shows can never disagree.
   *
   * @param int $current
   *   The chosen point.
   * @param int $min
   *   The lowest point.
   * @param int $max
   *   The highest point.
   * @param string $caption
   *   What the chosen point is called, or an empty string when it has no name.
   *
   * @return string
   *   The composed run.
   */
  public function fieldScale(int $current, int $min, int $max, string $caption): string;

  /**
   * The mark saying the field is still fetching what it will offer.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldLoading(): string;

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
