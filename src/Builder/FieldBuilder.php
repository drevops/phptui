<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Block\DateBounds;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\FilePickerConstraints;
use DrevOps\Tui\Block\FilePickerMode;
use DrevOps\Tui\Block\NumberBounds;
use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Block\Template;
use DrevOps\Tui\Block\Weekday;
use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\FormException;

/**
 * A fluent builder for a single field: the block that collects.
 *
 * A declaration is written onto the field as it is made. A declaration
 * stated in parts - a range, a shape, a set of limits - is assembled when
 * the declaration is finished, because only a finished one can be checked
 * for contradictions.
 *
 * Most declarations apply to some kinds of answer and not to others, and one
 * the kind has nowhere to put is rejected where it was written rather than
 * quietly dropped, so the error points at the line that made it.
 *
 * @package DrevOps\Tui\Builder
 */
final class FieldBuilder {

  /**
   * The lowest point of a rating scale that declares no minimum.
   */
  protected const int RATING_MIN = 1;

  /**
   * The highest point of a rating scale that declares no maximum.
   */
  protected const int RATING_MAX = 5;

  /**
   * The field carrying the declaration.
   */
  protected Field $block;

  /**
   * Whether an explicit default was set (otherwise the type default is used).
   */
  protected bool $hasDefault = FALSE;

  /**
   * The explicit default value, when set.
   */
  protected mixed $default = NULL;

  /**
   * The number field's inclusive minimum, when declared.
   */
  protected ?int $min = NULL;

  /**
   * The number field's inclusive maximum, when declared.
   */
  protected ?int $max = NULL;

  /**
   * The number field's Up/Down increment, when declared.
   */
  protected ?int $step = NULL;

  /**
   * Multiple only: the minimum number of selections, when declared.
   */
  protected ?int $minSelections = NULL;

  /**
   * Multiple only: the maximum number of selections, when declared.
   */
  protected ?int $maxSelections = NULL;

  /**
   * File picker only: which entries may be selected.
   */
  protected FilePickerMode $pickerMode = FilePickerMode::Any;

  /**
   * File picker only: the extensions selectable files are limited to.
   *
   * @var list<string>
   */
  protected array $pickerExtensions = [];

  /**
   * File picker only: the maximum selectable file size in bytes, when declared.
   */
  protected ?int $pickerMaxSize = NULL;

  /**
   * The date field's inclusive earliest date, when declared.
   */
  protected ?\DateTimeImmutable $minDate = NULL;

  /**
   * The date field's inclusive latest date, when declared.
   */
  protected ?\DateTimeImmutable $maxDate = NULL;

  /**
   * The date field's week-start day, when declared.
   */
  protected ?Weekday $weekStart = NULL;

  /**
   * Template only: the fixed shape whose slots are filled in, when declared.
   */
  protected string $pattern = '';

  /**
   * Template only: the human label of each slot, keyed by slot name.
   *
   * @var array<string,string>
   */
  protected array $slotLabels = [];

  /**
   * Template only: the validator of each slot, keyed by slot name.
   *
   * @var array<string,\Closure>
   */
  protected array $slotValidators = [];

  /**
   * Construct a field builder.
   *
   * @param string $id
   *   The unique field id.
   * @param string $label
   *   The human-readable label.
   * @param \DrevOps\Tui\Block\FieldType $fieldType
   *   The field type.
   */
  public function __construct(protected string $id, protected string $label, protected FieldType $fieldType) {
    $this->block = new Field($id, $label, $fieldType);
  }

  /**
   * The block this builder is declaring.
   *
   * @return \DrevOps\Tui\Block\Field
   *   The field, whose identity never changes, so it can be placed in a region
   *   as it is declared.
   */
  public function block(): Field {
    return $this->block;
  }

  /**
   * Finish the declaration, writing what was stated in parts onto the block.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the finished declaration contradicts itself.
   */
  public function seal(): void {
    $default = $this->resolveDefault();
    $bounds = $this->buildBounds();
    $picker = $this->buildPickerConstraints();
    $dates = $this->buildDateBounds();
    $selections = $this->buildSelectionBounds();
    $template = $this->buildTemplate();

    $this->block->default($default)->picker($picker);

    if ($bounds instanceof NumberBounds) {
      $this->block->bounds($bounds);
    }

    if ($dates instanceof DateBounds) {
      $this->block->dates($dates);
    }

    if ($selections instanceof SelectionBounds) {
      $this->block->selections($selections);
    }

    if ($template instanceof Template) {
      $this->block->pattern($template);
    }
  }

  /**
   * Set the help text.
   *
   * @param string $description
   *   The help text.
   *
   * @return $this
   *   The builder.
   */
  public function description(string $description): self {
    $this->block->description($description);

    return $this;
  }

  /**
   * Set the help: the long-form text behind the field's help key.
   *
   * A description has to fit under the row; help opens on its own page, so it
   * can run to paragraphs and hold the detail a row has no space for.
   *
   * @param string $help
   *   The help text; blank lines separate paragraphs.
   *
   * @return $this
   *   The builder.
   */
  public function help(string $help): self {
    $this->block->help($help);

    return $this;
  }

  /**
   * Set the ghost text shown inside the editor while its buffer is empty.
   *
   * A placeholder never becomes a value: it disappears as soon as anything is
   * typed.
   *
   * @param string $placeholder
   *   The ghost text (e.g. "E.g. Golden Beetroot").
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field draws no input buffer to ghost.
   */
  public function placeholder(string $placeholder): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsPlaceholder(), 'draws no input buffer to ghost', 'placeholder');
    $this->block->placeholder($placeholder);

    return $this;
  }

  /**
   * Set the default value.
   *
   * @param mixed $default
   *   The default value, or a `fn (Context): mixed` closure computing a
   *   dynamic default from the run context. A closure that cannot be
   *   evaluated without answers reads as null in machine-readable output
   *   unless a {@see schemaDefault()} replaces it there.
   *
   * @return $this
   *   The builder.
   */
  public function default(mixed $default): self {
    $this->hasDefault = TRUE;
    $this->default = $default;

    return $this;
  }

  /**
   * Set a representative default for machine-readable output.
   *
   * When {@see default()} is a closure that cannot be resolved without answers,
   * the schema and agent-help generators advertise this static value instead of
   * evaluating the closure. Consulted only for a closure default.
   *
   * @param mixed $default
   *   The static value to advertise as the default.
   *
   * @return $this
   *   The builder.
   */
  public function schemaDefault(mixed $default): self {
    $this->block->schemaDefault($default);

    return $this;
  }

  /**
   * Set the environment variable that answers the field.
   *
   * The name is absolute: the form's prefix is not applied to it, so a
   * variable name published elsewhere can be reproduced exactly. It replaces
   * the mechanical `<PREFIX><FIELD_ID>` name rather than adding to it;
   * declare the mechanical name through {@see envAliases()} so it still
   * answers the field.
   *
   * @param string $name
   *   The variable name.
   *
   * @return $this
   *   The builder.
   */
  public function env(string $name): self {
    $this->block->env($name);

    return $this;
  }

  /**
   * Set further environment variables that also answer the field.
   *
   * Each is absolute, like {@see env()}, and they are consulted in order after
   * the canonical name - so the canonical name wins when both are set, and a
   * naming scheme can change without breaking the variables already published.
   *
   * @param array<array-key,string> $names
   *   The alias names, most preferred first; reindexed as declared.
   *
   * @return $this
   *   The builder.
   */
  public function envAliases(array $names): self {
    $this->block->envAliases($names);

    return $this;
  }

  /**
   * Mark the field required, rejecting an empty value.
   *
   * @param bool $required
   *   Whether a value is required.
   * @param string $message
   *   The message shown when the value is empty; empty derives one from the
   *   label.
   *
   * @return $this
   *   The builder.
   */
  public function required(bool $required = TRUE, string $message = ''): self {
    $this->block->required($required, $message);

    return $this;
  }

  /**
   * Collect several values as a list rather than a single value.
   *
   * Honoured by the select, search and file picker types; a reorder field
   * already collects a full ranking and does not take this modifier.
   *
   * @param bool $multiple
   *   Whether the field collects several values.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field type does not support collecting several values.
   */
  public function multiple(bool $multiple = TRUE): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsMultiple(), 'does not collect several values', 'multiple');
    $this->block->multiple($multiple);

    return $this;
  }

  /**
   * Password only: offer a reveal/hide toggle over the masked value.
   *
   * @param bool $revealable
   *   Whether the toggle is enabled.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field masks nothing to reveal.
   */
  public function revealable(bool $revealable = TRUE): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Password, 'masks nothing to reveal', 'revealable');
    $this->block->revealable($revealable);

    return $this;
  }

  /**
   * Password only: ask twice and reject a mismatch before accepting.
   *
   * @param bool $confirm
   *   Whether confirmation mode is enabled.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field keeps no secret to confirm.
   */
  public function confirmation(bool $confirm = TRUE): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Password, 'keeps no secret to confirm', 'confirmation');
    $this->block->confirmation($confirm);

    return $this;
  }

  /**
   * Textarea only: allow the field to hand off to the user's $EDITOR.
   *
   * An available $EDITOR (or $VISUAL) can be launched to compose the value,
   * falling back to inline editing otherwise.
   *
   * @param bool $enabled
   *   Whether the external-editor handoff is offered.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field composes no long-form text to hand off.
   */
  public function externalEditor(bool $enabled = TRUE): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Textarea, 'composes no long-form text to hand off', 'externalEditor');
    $this->block->externalEditor($enabled);

    return $this;
  }

  /**
   * Edit the field on its own full-screen editor rather than inline.
   *
   * A field is edited inline by default - its editor expands in place on the
   * panel when activated, and collapses back on accept or cancel. Declaring it
   * standalone opens that same editor full-screen instead: the better fit for
   * a field that needs the whole viewport, such as a long option list, a
   * month calendar or a multi-line textarea.
   *
   * @param bool $standalone
   *   TRUE for the full-screen editor; FALSE to restore inline editing.
   *
   * @return $this
   *   The builder.
   */
  public function standalone(bool $standalone = TRUE): self {
    $this->block->standalone($standalone);

    return $this;
  }

  /**
   * Number and rating only: set the inclusive minimum accepted value.
   *
   * @param int $min
   *   The minimum.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field counts through no numbers.
   */
  public function min(int $min): self {
    $this->scoped(self::counts(), 'counts through no numbers', 'min');
    $this->min = $min;

    return $this;
  }

  /**
   * Number and rating only: set the inclusive maximum accepted value.
   *
   * @param int $max
   *   The maximum.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field counts through no numbers.
   */
  public function max(int $max): self {
    $this->scoped(self::counts(), 'counts through no numbers', 'max');
    $this->max = $max;

    return $this;
  }

  /**
   * Number only: set the Up/Down increment.
   *
   * @param int $step
   *   The step; must be positive.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the step is not positive, or the field draws a scale whose points
   *   are already its steps.
   */
  public function step(int $step): self {
    // Checked before the kind check: a scale does count through numbers, so
    // the error here is the increment rather than the kind.
    if ($this->fieldType === FieldType::Rating) {
      throw new FormException(sprintf('Field "%s" declares a step of %d on a scale whose points are its steps; remove ->step() and set the ends with ->min() and ->max().', $this->id, $step));
    }

    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Number, 'counts through no numbers', 'step');

    if ($step < 1) {
      throw new FormException(sprintf('Field "%s" declares a non-positive step %d.', $this->id, $step));
    }

    $this->step = $step;

    return $this;
  }

  /**
   * Rating only: caption points on the scale.
   *
   * A caption names what a point means ("Poor", "Excellent") and is shown
   * while that point is chosen. Points may be captioned sparsely - captioning
   * only the two ends labels the scale without claiming a reading for every
   * step in between - and an uncaptioned point still answers with its number.
   *
   * @param array<int,string> $captions
   *   The caption of each point, keyed by the point; every key must be within
   *   the scale.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field draws no scale to caption.
   */
  public function captions(array $captions): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Rating, 'draws no scale to caption', 'captions');
    $this->block->captions($captions);

    return $this;
  }

  /**
   * Multiple only: require at least this many selections.
   *
   * @param int $min
   *   The minimum number of selections.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field does not collect several values.
   */
  public function minSelections(int $min): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsMultiple(), 'does not collect several values', 'minSelections');
    $this->minSelections = $min;

    return $this;
  }

  /**
   * Multiple only: allow at most this many selections.
   *
   * @param int $max
   *   The maximum number of selections.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field does not collect several values.
   */
  public function maxSelections(int $max): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsMultiple(), 'does not collect several values', 'maxSelections');
    $this->maxSelections = $max;

    return $this;
  }

  /**
   * File picker only: set the directory the browser opens at.
   *
   * The browser cannot ascend above this directory, so it also bounds where a
   * path may be chosen. An empty value falls back to the current working
   * directory at collection time.
   *
   * @param string $directory
   *   The start directory.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem.
   */
  public function startIn(string $directory): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'startIn');
    $this->block->startIn($directory);

    return $this;
  }

  /**
   * File picker only: allow only files to be selected.
   *
   * Directories stay navigable so files beneath them remain reachable.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem.
   */
  public function filesOnly(): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'filesOnly');
    $this->pickerMode = FilePickerMode::File;

    return $this;
  }

  /**
   * File picker only: allow only directories to be selected.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem.
   */
  public function directoriesOnly(): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'directoriesOnly');
    $this->pickerMode = FilePickerMode::Directory;

    return $this;
  }

  /**
   * File picker only: limit selectable files to the given extensions.
   *
   * @param list<string> $extensions
   *   The allowed extensions (dot-less, case-insensitive); empty allows every
   *   extension.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem.
   */
  public function extensions(array $extensions): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'extensions');
    $this->pickerExtensions = $extensions;

    return $this;
  }

  /**
   * File picker only: show dot-entries when the browser opens.
   *
   * @param bool $show
   *   Whether hidden entries are shown initially.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem.
   */
  public function showHidden(bool $show = TRUE): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'showHidden');
    $this->block->showHidden($show);

    return $this;
  }

  /**
   * File picker only: reject any selected file larger than this many bytes.
   *
   * Applies to files, not directories, and is enforced identically on an
   * interactive accept and a headless path.
   *
   * @param int $bytes
   *   The inclusive maximum file size in bytes; must be positive.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field browses no filesystem, or the size is not positive.
   */
  public function maxSize(int $bytes): self {
    $this->scoped(self::browses(), 'browses no filesystem', 'maxSize');

    if ($bytes < 1) {
      throw new FormException(sprintf('Field "%s" declares a maximum file size of %d below one byte.', $this->id, $bytes));
    }

    $this->pickerMaxSize = $bytes;

    return $this;
  }

  /**
   * List fields only: bound the visible option list to a page size.
   *
   * Longer lists page around the cursor rather than overflowing the viewport.
   *
   * @param int $size
   *   The number of option rows shown at once; must be positive.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field draws no list to page, or the size is not positive.
   */
  public function pageSize(int $size): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsOptions() || $type === FieldType::FilePicker, 'draws no list to page', 'pageSize');

    if ($size < 1) {
      throw new FormException(sprintf('Field "%s" declares a non-positive page size %d.', $this->id, $size));
    }

    $this->block->paginate($size);

    return $this;
  }

  /**
   * Calendar only: set the inclusive earliest selectable date.
   *
   * @param string $date
   *   The earliest date, as an ISO `Y-m-d` string.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field picks no date, or the value is not a valid `Y-m-d` date.
   */
  public function minDate(string $date): self {
    $this->scoped(self::picksDate(), 'picks no date', 'minDate');
    $this->minDate = $this->parseBoundDate($date);

    return $this;
  }

  /**
   * Calendar only: set the inclusive latest selectable date.
   *
   * @param string $date
   *   The latest date, as an ISO `Y-m-d` string.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field picks no date, or the value is not a valid `Y-m-d` date.
   */
  public function maxDate(string $date): self {
    $this->scoped(self::picksDate(), 'picks no date', 'maxDate');
    $this->maxDate = $this->parseBoundDate($date);

    return $this;
  }

  /**
   * Calendar only: set the day the calendar week begins on.
   *
   * @param \DrevOps\Tui\Block\Weekday $weekday
   *   The week-start day.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field picks no date.
   */
  public function weekStart(Weekday $weekday): self {
    $this->scoped(self::picksDate(), 'picks no date', 'weekStart');
    $this->weekStart = $weekday;

    return $this;
  }

  /**
   * Set the conditional-visibility rule.
   *
   * @param \DrevOps\Tui\Condition\ConditionInterface $condition
   *   The condition gating the field, evaluated as the answers settle.
   *
   * @return $this
   *   The builder.
   */
  public function when(ConditionInterface $condition): self {
    $this->block->when($condition);

    return $this;
  }

  /**
   * Set the derive rule.
   *
   * @param \DrevOps\Tui\Derive\Derive $derive
   *   The derive rule, evaluated as the answers settle.
   *
   * @return $this
   *   The builder.
   */
  public function derive(Derive $derive): self {
    $this->block->derive($derive);

    return $this;
  }

  /**
   * Set the discovery rule.
   *
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure $discover
   *   The discovery rule - or a custom `fn (Context): mixed` detector -
   *   evaluated in update mode.
   *
   * @return $this
   *   The builder.
   */
  public function discover(DiscoverInterface|\Closure $discover): self {
    $this->block->discover($discover);

    return $this;
  }

  /**
   * Set the declared validator.
   *
   * @param \Closure $validator
   *   The validator `fn (mixed $value): ?string` returning an error message,
   *   or NULL when the value is valid.
   *
   * @return $this
   *   The builder.
   */
  public function validate(\Closure $validator): self {
    $this->block->validate($validator);

    return $this;
  }

  /**
   * Set the declared transformer.
   *
   * @param \Closure $transformer
   *   The transformer `fn (mixed $value): mixed` normalizing an accepted
   *   value.
   *
   * @return $this
   *   The builder.
   */
  public function transform(\Closure $transformer): self {
    $this->block->transform($transformer);

    return $this;
  }

  /**
   * Text only: set the inline ghost-text completion source.
   *
   * As the user types, the first candidate the input is a prefix of is shown
   * dimmed after the caret and accepted with Tab or Right-arrow.
   *
   * @param list<string>|\Closure $source
   *   A list of candidate strings, or a
   *   `fn (array<string,mixed> $answers): list<string>` closure computing
   *   candidates from the answers collected so far.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field completes no typed text.
   */
  public function complete(array|\Closure $source): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Text, 'completes no typed text', 'complete');
    $this->block->complete($source);

    return $this;
  }

  /**
   * Suggest only: preview the leading prefix match as inline ghost-text.
   *
   * As the user types, the highest-ranked option the input is a prefix of is
   * shown dimmed after the caret and accepted with Tab or Right-arrow. The
   * ranked list stays available; the preview is suppressed once a suggestion is
   * highlighted, and whenever colour is off.
   *
   * @param bool $ghost
   *   Whether the preview is shown.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field ranks no options to preview.
   */
  public function ghost(bool $ghost = TRUE): self {
    $this->scoped(static fn(FieldType $type): bool => $type === FieldType::Suggest, 'ranks no options to preview', 'ghost');
    $this->block->ghost($ghost);

    return $this;
  }

  /**
   * Template only: set the fixed shape whose slots the field fills in.
   *
   * The pattern carries `{{name}}` slots: its fixed text renders as context and
   * each slot is filled in separately. Two slots must be separated by some
   * fixed text, so a filled string can be read back into its parts.
   *
   * @param string $pattern
   *   The pattern, e.g. `{{orchard}}-{{fruit}}-{{grade}}`.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field fills in no fixed shape.
   */
  public function pattern(string $pattern): self {
    $this->scoped(self::fillsShape(), 'fills in no fixed shape', 'pattern');
    $this->pattern = $pattern;

    return $this;
  }

  /**
   * Template only: label and validate one of the pattern's slots.
   *
   * @param string $name
   *   The slot name, as it appears in the pattern.
   * @param string $label
   *   The human label shown while the slot is being filled; defaults to the
   *   slot name.
   * @param \Closure|null $validate
   *   The validator `fn (string $value): ?string` returning an error message,
   *   or NULL when the part is valid. Runs on its own, independently of every
   *   other slot and of the field's own validator.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field fills in no fixed shape.
   */
  public function slot(string $name, string $label = '', ?\Closure $validate = NULL): self {
    $this->scoped(self::fillsShape(), 'fills in no fixed shape', 'slot');

    if ($label !== '') {
      $this->slotLabels[$name] = $label;
    }

    if ($validate instanceof \Closure) {
      $this->slotValidators[$name] = $validate;
    }

    return $this;
  }

  /**
   * Add a single option.
   *
   * @param string $value
   *   The option value.
   * @param string $label
   *   The option label (defaults to the value).
   * @param string $description
   *   The option description.
   * @param bool $disabled
   *   Whether the option is shown but cannot be selected.
   * @param string $disabled_reason
   *   The reason shown beside a disabled option.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field shows no options.
   */
  public function option(string $value, string $label = '', string $description = '', bool $disabled = FALSE, string $disabled_reason = ''): self {
    $this->scoped(self::lists(), 'shows no options', 'option');
    $this->block->entry($value, $label, $description, $disabled, $disabled_reason);

    return $this;
  }

  /**
   * Add a non-selectable separator row.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field shows no options.
   */
  public function separator(): self {
    $this->scoped(self::lists(), 'shows no options', 'separator');
    $this->block->separator();

    return $this;
  }

  /**
   * Add a non-selectable group heading row.
   *
   * @param string $label
   *   The heading label.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field shows no options.
   */
  public function heading(string $label): self {
    $this->scoped(self::lists(), 'shows no options', 'heading');
    $this->block->heading($label);

    return $this;
  }

  /**
   * Add several options from a value => label map, or a callback for them.
   *
   * The callback's signature determines when it runs. One that takes the run
   * context follows the collected answers: it is called again whenever they
   * change, so one field's choices can narrow by another's answer. It runs as
   * part of the form settling, before anything is drawn or validated, so the
   * narrowed set is the one every surface sees: the panel, headless
   * collection, the schema and the validator. A value the narrowed set no
   * longer offers is dropped from the answers - a ranking is completed back to
   * a full permutation and a toggle falls back to its first state - unless it
   * was supplied headlessly, which is reported instead. Keep such a callback
   * cheap: it runs for the whole form, not once per panel.
   *
   * A callback with no parameters loads one list, once, lazily when the
   * field's panel opens - showing a themed "Loading…" beside the field until
   * it returns, and running after the panel's `->preload()` so it can read
   * what preload prepared.
   *
   * @param array<array-key,string>|\Closure $options
   *   The options keyed by value with a label, or a callback returning that
   *   same map: `fn (Context $context): array<string,string>` to follow the
   *   answers, or `fn (): array<string,string>` to load them once. Either way
   *   a toggle or reorder derives its default from the options, so one whose
   *   options arrive later should declare an explicit `->default()`.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field shows no options.
   */
  public function options(array|\Closure $options): self {
    $this->scoped(self::lists(), 'shows no options', 'options');

    if ($options instanceof \Closure) {
      // The signature is read here rather than at every call, so the
      // lifecycle is chosen without a reflection call mid-session.
      if ((new \ReflectionFunction($options))->getNumberOfParameters() > 0) {
        $this->block->resolve($options);
      }
      else {
        $this->block->load($options);
      }

      return $this;
    }

    foreach ($options as $value => $label) {
      $this->option((string) $value, $label);
    }

    return $this;
  }

  /**
   * Search and suggest only: source the options from the live query.
   *
   * Where `->options()` resolves one fixed list, a query source is called again
   * every time the query changes, so the candidates can come from a backend
   * that does the filtering itself - a remote search, a database lookup, an
   * index. The field shows a themed "Loading…" while a call is in flight, and
   * the result replaces the list wholesale, so the local filter is turned off.
   *
   * A query already answered in this editor session is served from a cache,
   * and a burst of typing resolves once, not once per character.
   *
   * @param \Closure $source
   *   An
   *   `fn (string $query, array<string,mixed> $answers): array<string,string>`
   *   returning the options for the given query, keyed by value with a label -
   *   the shape `->options()` takes. It also receives the answers collected so
   *   far, so one field's query can narrow by another's answer.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field runs no query.
   */
  public function optionsFrom(\Closure $source): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsQuerySource(), 'runs no query', 'optionsFrom');
    $this->block->query($source);

    return $this;
  }

  /**
   * Set the query length below which the query source is not called.
   *
   * A remote source asked for the empty query has to answer with everything,
   * so no query shorter than the floor is sent. Below the floor the field
   * shows a prompt to keep typing instead of a list.
   *
   * @param int $length
   *   The minimum number of characters, at least one.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the field runs no query, or the length is below one character.
   */
  public function minQuery(int $length): self {
    $this->scoped(static fn(FieldType $type): bool => $type->supportsQuerySource(), 'runs no query', 'minQuery');

    if ($length < 1) {
      throw new FormException(sprintf('Field "%s" declares a minimum query length of %d; it must be at least one character (omit minQuery() to query on every keystroke).', $this->id, $length));
    }

    $this->block->minQuery($length);

    return $this;
  }

  /**
   * The kinds whose answer is a point on a numeric range.
   *
   * @return \Closure
   *   The `fn (FieldType $type): bool` naming them.
   */
  protected static function counts(): \Closure {
    return static fn(FieldType $type): bool => $type->collectsInteger();
  }

  /**
   * The kinds that draw a list of options.
   *
   * @return \Closure
   *   The `fn (FieldType $type): bool` naming them.
   */
  protected static function lists(): \Closure {
    return static fn(FieldType $type): bool => $type->supportsOptions();
  }

  /**
   * The kinds that browse the filesystem.
   *
   * @return \Closure
   *   The `fn (FieldType $type): bool` naming them.
   */
  protected static function browses(): \Closure {
    return static fn(FieldType $type): bool => $type === FieldType::FilePicker;
  }

  /**
   * The kinds that pick a calendar date.
   *
   * @return \Closure
   *   The `fn (FieldType $type): bool` naming them.
   */
  protected static function picksDate(): \Closure {
    return static fn(FieldType $type): bool => $type === FieldType::Calendar;
  }

  /**
   * The kinds that fill the slots of a fixed shape.
   *
   * @return \Closure
   *   The `fn (FieldType $type): bool` naming them.
   */
  protected static function fillsShape(): \Closure {
    return static fn(FieldType $type): bool => $type === FieldType::Template;
  }

  /**
   * Refuse a declaration this field's kind has nowhere to put.
   *
   * @param \Closure $applies
   *   An `fn (FieldType $type): bool` naming the kinds the declaration applies
   *   to. The refusal lists them from it, so the message can never drift from
   *   the check it explains.
   * @param string $lacks
   *   What this field has not got, as a fragment ("shows no options").
   * @param string $method
   *   The refused method, without its parentheses.
   *
   * @throws \DrevOps\Tui\FormException
   *   When this field's kind is not one the declaration applies to.
   */
  protected function scoped(\Closure $applies, string $lacks, string $method): void {
    if ($applies($this->fieldType) === TRUE) {
      return;
    }

    $kinds = array_map(static fn(FieldType $type): string => $type->value, array_filter(FieldType::cases(), $applies));

    throw new FormException(sprintf('Field "%s" of type "%s" %s; ->%s() applies to %s.', $this->id, $this->fieldType->value, $lacks, $method, implode(', ', $kinds)));
  }

  /**
   * Assemble the template of a template field from the declared pattern.
   *
   * @return \DrevOps\Tui\Block\Template|null
   *   The template, or NULL for any other field type or an absent pattern.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the pattern cannot be filled in or read back unambiguously.
   */
  protected function buildTemplate(): ?Template {
    if ($this->fieldType !== FieldType::Template || $this->pattern === '') {
      return NULL;
    }

    return new Template($this->pattern, $this->slotLabels, $this->slotValidators);
  }

  /**
   * The effective default: the declared one, or the type's implicit default.
   *
   * @return mixed
   *   The default value.
   */
  protected function resolveDefault(): mixed {
    // A reorder default is always completed to a full ranking, so even a
    // partial or unset declared order resolves to every option in sequence.
    if ($this->fieldType === FieldType::Reorder) {
      return $this->reorderDefault();
    }

    if ($this->hasDefault) {
      return $this->default;
    }

    // A multiple choice or file picker collects a list, so with nothing
    // declared it defaults to no values.
    if ($this->block->isMultiple()) {
      return [];
    }

    // A rating always sits on a point of its scale, so it starts at the lowest
    // one rather than at a zero that may not even be on the scale.
    if ($this->fieldType === FieldType::Rating) {
      return $this->min ?? self::RATING_MIN;
    }

    // A toggle is always in one of its two states, so it defaults to the first
    // option's value rather than an empty value that would not match either.
    // The value is read off the option, not its array key, so a numeric-string
    // value like "0" is not coerced to an int.
    $entries = $this->block->entries();

    if ($this->fieldType === FieldType::Toggle && $entries !== []) {
      return reset($entries)->value;
    }

    return $this->defaultFor($this->fieldType);
  }

  /**
   * The reorder field's default: the declared order completed to a full rank.
   *
   * @return list<string>
   *   Every selectable option value, the declared default order first and the
   *   remaining options appended in declared order.
   */
  protected function reorderDefault(): array {
    $values = $this->block->selectableValues();
    $desired = $this->hasDefault ? Field::stringList($this->default) : [];

    return Field::canonicalOrder($values, $desired);
  }

  /**
   * Assemble the number bounds from the declared min/max/step, if any.
   *
   * @return \DrevOps\Tui\Block\NumberBounds|null
   *   The bounds, or NULL when none were declared.
   *
   * @throws \DrevOps\Tui\FormException
   *   When min exceeds max.
   */
  protected function buildBounds(): ?NumberBounds {
    if ($this->fieldType === FieldType::Rating) {
      return $this->buildScale();
    }

    if ($this->min === NULL && $this->max === NULL && $this->step === NULL) {
      return NULL;
    }

    if ($this->min !== NULL && $this->max !== NULL && $this->min > $this->max) {
      throw new FormException(sprintf('Field "%s" declares min %d greater than max %d.', $this->id, $this->min, $this->max));
    }

    return new NumberBounds($this->min, $this->max, $this->step);
  }

  /**
   * Assemble a rating's scale: a closed range, defaulting to one through five.
   *
   * Unlike a number, a rating is never open-ended - the points are what it
   * draws - so both ends always resolve, and the arrows walk the range one
   * point at a time rather than by a declared increment.
   *
   * @return \DrevOps\Tui\Block\NumberBounds
   *   The scale.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the range holds fewer than two points.
   */
  protected function buildScale(): NumberBounds {
    $min = $this->min ?? self::RATING_MIN;
    $max = $this->max ?? self::RATING_MAX;

    if ($min >= $max) {
      throw new FormException(sprintf('Field "%s" declares a scale from %d to %d; a scale needs at least two points, so its maximum must be above its minimum.', $this->id, $min, $max));
    }

    return new NumberBounds($min, $max);
  }

  /**
   * Assemble the selection bounds from the declared min/max selections, if any.
   *
   * @return \DrevOps\Tui\Block\SelectionBounds|null
   *   The bounds, or NULL when no selection limit was declared.
   *
   * @throws \DrevOps\Tui\FormException
   *   When selection limits are declared on a non-multiple field, or the
   *   minimum exceeds the maximum.
   */
  protected function buildSelectionBounds(): ?SelectionBounds {
    if ($this->minSelections === NULL && $this->maxSelections === NULL) {
      return NULL;
    }

    if (!$this->block->isMultiple()) {
      throw new FormException(sprintf('Field "%s" declares selection limits but is not multiple; ->minSelections()/->maxSelections() apply to multiple select, search and file picker fields.', $this->id));
    }

    if ($this->minSelections !== NULL && $this->maxSelections !== NULL && $this->minSelections > $this->maxSelections) {
      throw new FormException(sprintf('Field "%s" declares min %d selections greater than max %d.', $this->id, $this->minSelections, $this->maxSelections));
    }

    return new SelectionBounds($this->minSelections, $this->maxSelections);
  }

  /**
   * Assemble the file picker constraints from the declared type/extension/size.
   *
   * @return \DrevOps\Tui\Block\FilePickerConstraints
   *   The constraints; unconstrained when nothing was declared.
   */
  protected function buildPickerConstraints(): FilePickerConstraints {
    return new FilePickerConstraints($this->pickerMode, $this->pickerExtensions, $this->pickerMaxSize);
  }

  /**
   * Assemble the date bounds for a date field from the declared min/max/start.
   *
   * @return \DrevOps\Tui\Block\DateBounds|null
   *   The bounds for a date field, or NULL for any other field type.
   *
   * @throws \DrevOps\Tui\FormException
   *   When a declared date is not a valid `Y-m-d` date, or min is after max.
   */
  protected function buildDateBounds(): ?DateBounds {
    if ($this->fieldType !== FieldType::Calendar) {
      return NULL;
    }

    $min = $this->minDate;
    $max = $this->maxDate;

    if ($min instanceof \DateTimeImmutable && $max instanceof \DateTimeImmutable && $min > $max) {
      throw new FormException(sprintf('Field "%s" declares min date %s after max date %s.', $this->id, $min->format('Y-m-d'), $max->format('Y-m-d')));
    }

    return new DateBounds($min, $max, $this->weekStart ?? Weekday::Monday);
  }

  /**
   * Strictly parse a declared bound date, failing loudly on a bad value.
   *
   * @param string $value
   *   The declared date string.
   *
   * @return \DateTimeImmutable
   *   The parsed date.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the value is not a valid `Y-m-d` date.
   */
  protected function parseBoundDate(string $value): \DateTimeImmutable {
    $date = DateBounds::parse($value);

    if (!$date instanceof \DateTimeImmutable) {
      throw new FormException(sprintf('Field "%s" declares an invalid date "%s".', $this->id, $value));
    }

    return $date;
  }

  /**
   * The default a field type settles on when none is declared.
   *
   * @param \DrevOps\Tui\Block\FieldType $type
   *   The field type.
   *
   * @return mixed
   *   The type default.
   */
  protected function defaultFor(FieldType $type): mixed {
    return match ($type) {
      FieldType::Confirm => FALSE,
      FieldType::Number => 0,
      // A pause is an interactive acknowledgement; headless runs have nothing
      // to wait for, so it defaults to acknowledged.
      FieldType::Pause => TRUE,
      default => '',
    };
  }

}
