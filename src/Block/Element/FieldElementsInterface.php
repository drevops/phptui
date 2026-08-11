<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the field block composes.
 *
 * One field owns both of its modes, so one interface names both: the first
 * group is the single line a field draws until it is opened, and the second is
 * what it draws in the region it takes over once it is. A pair such as the
 * value and the draft, or the description and the option description, is two
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
   * Style one option of a list a field opens onto.
   *
   * Being picked and being where the cursor rests are two different facts
   * about an option, and an option can be either, both or neither: a settled
   * row draws what was picked with no cursor anywhere in it, and a cursor
   * moving down an open list picks nothing.
   *
   * @param string $text
   *   The option label.
   * @param bool $chosen
   *   Whether it is picked.
   * @param bool $focused
   *   Whether the cursor rests on it.
   *
   * @return string
   *   The styled option.
   */
  public function fieldOption(string $text, bool $chosen, bool $focused = FALSE): string;

  /**
   * Style the run of an option's label that answers what was typed.
   *
   * @param string $text
   *   The matched run.
   *
   * @return string
   *   The styled run.
   */
  public function fieldOptionMatch(string $text): string;

  /**
   * The mark saying which option has focus.
   *
   * Its own element rather than a reuse of the field selector: one says which
   * field you are on and the other which option within it, so a theme can
   * restyle either without touching the other.
   *
   * @param bool $selected
   *   Whether this is the option the cursor is on.
   *
   * @return string
   *   The styled mark, or the gap standing in its place.
   */
  public function fieldOptionSelector(bool $selected): string;

  /**
   * The mark recording that an option was picked.
   *
   * Selecting and marking are different things: a selector says where you are,
   * and this says what you decided. Whether picking one option unpicks the last
   * is a fact about the question rather than about the mark, so the mark is
   * told - a question that takes one answer and a question that takes several
   * are worth telling apart before anything has been picked at all.
   *
   * @param bool $chosen
   *   Whether the option is picked.
   * @param bool $exclusive
   *   Whether picking this option gives up every other, rather than adding to
   *   what is already picked.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldOptionMarker(bool $chosen, bool $exclusive = FALSE): string;

  /**
   * Style a qualifier on an option, such as why it is unavailable.
   *
   * @param string $text
   *   The note.
   *
   * @return string
   *   The styled note.
   */
  public function fieldOptionNote(string $text): string;

  /**
   * Style the focused option's own explanatory text.
   *
   * @param string $text
   *   The description.
   *
   * @return string
   *   The styled description.
   */
  public function fieldOptionDescription(string $text): string;

  /**
   * The mark standing between two runs of options.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldOptionSeparator(): string;

  /**
   * The mark saying a list runs past the page it is windowed to.
   *
   * Its own element rather than a reuse of the chrome's: one says a list the
   * field owns outran its page and the other that a region outran the space it
   * was given, so a theme can restyle either without touching the other.
   *
   * @param bool $above
   *   Whether the options it points at are above rather than below.
   *
   * @return string
   *   The styled mark.
   */
  public function fieldOverflowMarker(bool $above): string;

  /**
   * Style what a field will accept, before anything is refused.
   *
   * The guidance voice, and the one line that has to survive a surface with
   * nothing to spend: it sits directly under an option's own explanatory text,
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
