<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Translation\Translator;

/**
 * The set of supported field (widget) types.
 *
 * @package DrevOps\Tui\Model
 */
enum FieldType: string {

  case Text = 'text';
  case Template = 'template';
  case Select = 'select';
  case Confirm = 'confirm';
  case Toggle = 'toggle';
  case Suggest = 'suggest';
  case Number = 'number';
  case Calendar = 'calendar';
  case Textarea = 'textarea';
  case Password = 'password';
  case Search = 'search';
  case Reorder = 'reorder';
  case FilePicker = 'filepicker';
  case Pause = 'pause';
  case Note = 'note';
  case Progress = 'progress';

  /**
   * The human label in the active language.
   *
   * A literal per case, rather than translating the backing value, so each
   * label is a discoverable chrome key in the catalog template.
   *
   * @return string
   *   The translated label.
   */
  public function label(): string {
    return match ($this) {
      self::Text => Translator::t('Text'),
      self::Template => Translator::t('Template'),
      self::Select => Translator::t('Select'),
      self::Confirm => Translator::t('Confirm'),
      self::Toggle => Translator::t('Toggle'),
      self::Suggest => Translator::t('Suggest'),
      self::Number => Translator::t('Number'),
      self::Calendar => Translator::t('Calendar'),
      self::Textarea => Translator::t('Textarea'),
      self::Password => Translator::t('Password'),
      self::Search => Translator::t('Search'),
      self::Reorder => Translator::t('Reorder'),
      self::FilePicker => Translator::t('File picker'),
      self::Pause => Translator::t('Pause'),
      self::Note => Translator::t('Note'),
      self::Progress => Translator::t('Progress'),
    };
  }

  /**
   * Whether the field only presents content and collects no answer.
   *
   * A presentational field renders inline but is display-only: the selection
   * cursor skips it, it carries no value in the answers payload, and it is
   * absent from the machine schemas.
   *
   * @return bool
   *   TRUE for the note field.
   */
  public function isPresentational(): bool {
    return $this === self::Note;
  }

  /**
   * Whether the field carries no answer into the payload or machine schema.
   *
   * Wider than {@see isPresentational()}: a note and a progress row each
   * collect no value, so the engine, the answers and the schema skip them - but
   * a progress row still takes the cursor (it runs work on activation), so it
   * is not presentational.
   *
   * @return bool
   *   TRUE for the display-only field types.
   */
  public function isDisplayOnly(): bool {
    return $this === self::Note || $this === self::Progress;
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
   * Only the types whose interaction *is* a query qualify, because a source
   * that follows the query has to show the query it followed: a select filters
   * a known set without displaying the filter, a reorder and a toggle need the
   * whole set at once, and a text field's ghost completion has no list to
   * resolve into.
   *
   * @return bool
   *   TRUE for the query-driven choice types.
   */
  public function supportsQuerySource(): bool {
    return in_array($this, [self::Search, self::Suggest], TRUE);
  }

}
