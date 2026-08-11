<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

/**
 * The kinds of answer a field collects.
 *
 * A field is the only block that collects, so every case names an answer a
 * reader can supply. A block that shows content or runs work is a block kind
 * of its own, not a kind of answer.
 *
 * @package DrevOps\Tui\Block
 */
enum FieldType: string {

  case Text = 'text';
  case Template = 'template';
  case Select = 'select';
  case Confirm = 'confirm';
  case Toggle = 'toggle';
  case Suggest = 'suggest';
  case Number = 'number';
  case Rating = 'rating';
  case Calendar = 'calendar';
  case Textarea = 'textarea';
  case Password = 'password';
  case Search = 'search';
  case Reorder = 'reorder';
  case FilePicker = 'filepicker';
  case Pause = 'pause';

  /**
   * Whether the field's answer is a whole number rather than text.
   *
   * @return bool
   *   TRUE for the integer-valued types.
   */
  public function collectsInteger(): bool {
    return $this === self::Number || $this === self::Rating;
  }

  /**
   * Whether a supplied value must be one of the field's selectable options.
   *
   * Suggest is excluded: its options are autocomplete hints, not a closed set.
   *
   * @return bool
   *   TRUE for the option-constrained choice types.
   */
  public function constrainsToOptions(): bool {
    return in_array($this, [
      self::Select,
      self::Search,
      self::Toggle,
      self::Reorder,
    ], TRUE);
  }

  /**
   * Whether a field of this type shows a list of options at all.
   *
   * Wider than {@see constrainsToOptions()}: a suggest field's options are
   * autocomplete hints rather than a closed set, but it still has a list to
   * declare.
   *
   * @return bool
   *   TRUE for every type that draws an option list.
   */
  public function supportsOptions(): bool {
    return in_array($this, [
      self::Select,
      self::Search,
      self::Suggest,
      self::Toggle,
      self::Reorder,
    ], TRUE);
  }

  /**
   * Whether a field of this type may collect several values via `->multiple()`.
   *
   * @return bool
   *   TRUE for the choice and file-picker types a multiple field builds on.
   */
  public function supportsMultiple(): bool {
    return in_array($this, [self::Select, self::Search, self::FilePicker], TRUE);
  }

  /**
   * Whether a field of this type may source its options from a live query.
   *
   * Only the types whose interaction is a query qualify: query-sourced
   * options must be shown beside the query that produced them.
   *
   * A select filters a known set without displaying the filter. A reorder and
   * a toggle need the whole set at once, and a text field's ghost completion
   * has no list to resolve into.
   *
   * @return bool
   *   TRUE for the query-driven choice types.
   */
  public function supportsQuerySource(): bool {
    return in_array($this, [self::Search, self::Suggest], TRUE);
  }

  /**
   * Whether a field of this type can show ghost text in an empty input.
   *
   * Template is excluded: each empty slot shows its own label as ghost text,
   * so a field-level placeholder would conflict with it.
   *
   * @return bool
   *   TRUE for the types that edit a text buffer or a query line.
   */
  public function supportsPlaceholder(): bool {
    return in_array($this, [
      self::Text,
      self::Number,
      self::Textarea,
      self::Password,
      self::Suggest,
      self::Search,
    ], TRUE);
  }

}
