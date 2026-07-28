<?php

declare(strict_types=1);

namespace DrevOps\Tui\Builder;

use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;

/**
 * A fluent builder for a single Field.
 *
 * @package DrevOps\Tui\Builder
 */
final class FieldBuilder {

  /**
   * The help text.
   */
  protected string $description = '';

  /**
   * How to answer the question.
   */
  protected string $hint = '';

  /**
   * The ghost text shown while the editor's buffer is empty.
   */
  protected string $placeholder = '';

  /**
   * Whether an explicit default was set (otherwise the type default is used).
   */
  protected bool $hasDefault = FALSE;

  /**
   * The explicit default value, when set.
   */
  protected mixed $default = NULL;

  /**
   * Whether a representative default for machine-readable output was set.
   */
  protected bool $hasSchemaDefault = FALSE;

  /**
   * The representative default shown in machine-readable output, when set.
   */
  protected mixed $schemaDefault = NULL;

  /**
   * The option rows, in display order.
   *
   * @var list<\DrevOps\Tui\Model\Option>
   */
  protected array $options = [];

  /**
   * A loader for the options, or NULL for static options.
   */
  protected ?\Closure $optionsLoader = NULL;

  /**
   * A query source for the options, or NULL when they do not follow the query.
   */
  protected ?\Closure $optionsSource = NULL;

  /**
   * The query length below which the query source is not called.
   */
  protected int $queryMinLength = 0;

  /**
   * The step count for a progress bar, or NULL for an indeterminate spinner.
   */
  protected ?int $progressSteps = NULL;

  /**
   * The work a progress row runs when activated, or NULL for none.
   */
  protected ?\Closure $progressWork = NULL;

  /**
   * Whether a value is required.
   */
  protected bool $required = FALSE;

  /**
   * The message shown when a required value is missing, empty to derive one.
   */
  protected string $requiredMessage = '';

  /**
   * Whether the field collects several values as a list rather than one.
   */
  protected bool $multiple = FALSE;

  /**
   * The conditional-visibility rule.
   */
  protected ?ConditionInterface $when = NULL;

  /**
   * The derive rule.
   */
  protected ?Derive $derive = NULL;

  /**
   * The discovery rule, or a custom detector closure.
   */
  protected DiscoverInterface|\Closure|null $discover = NULL;

  /**
   * The declared validator.
   */
  protected ?\Closure $validate = NULL;

  /**
   * The declared transformer.
   */
  protected ?\Closure $transform = NULL;

  /**
   * The inline ghost-text completion source (a list or a closure).
   *
   * @var list<string>|\Closure
   */
  protected array|\Closure $completion = [];

  /**
   * Whether a password editor offers a reveal/hide toggle.
   */
  protected bool $revealable = FALSE;

  /**
   * Whether a password editor prompts for the value twice.
   */
  protected bool $confirm = FALSE;

  /**
   * Whether the field may hand off to the user's $EDITOR.
   */
  protected bool $externalEditor = FALSE;

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
   * File picker only: the start directory and the floor it cannot ascend above.
   */
  protected string $pickerStart = '';

  /**
   * File picker only: the extensions selectable files are limited to.
   *
   * @var list<string>
   */
  protected array $pickerExtensions = [];

  /**
   * File picker only: whether dot-entries are shown when the browser opens.
   */
  protected bool $pickerShowHidden = FALSE;

  /**
   * File picker only: the maximum selectable file size in bytes, when declared.
   */
  protected ?int $pickerMaxSize = NULL;

  /**
   * Choice widgets only: the visible page size, when declared.
   */
  protected ?int $pageSize = NULL;

  /**
   * The date field's inclusive earliest date (ISO `Y-m-d`), when declared.
   */
  protected ?string $minDate = NULL;

  /**
   * The date field's inclusive latest date (ISO `Y-m-d`), when declared.
   */
  protected ?string $maxDate = NULL;

  /**
   * The date field's week-start day, when declared.
   */
  protected ?Weekday $weekStart = NULL;

  /**
   * Where the field's editor is drawn: inline in the panel, or full-screen.
   */
  protected RenderMode $render = RenderMode::Inline;

  /**
   * Note only: whether the card is drawn inside a themed border.
   */
  protected bool $bordered = FALSE;

  /**
   * Note only: a presentational table rendered beneath the card, when declared.
   */
  protected ?TableSpec $table = NULL;

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
   * @param \DrevOps\Tui\Model\FieldType $fieldType
   *   The widget type.
   */
  public function __construct(protected string $id, protected string $label, protected FieldType $fieldType) {
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
    $this->description = $description;

    return $this;
  }

  /**
   * Set the hint: how to answer the question.
   *
   * Sits beneath the description and is styled apart from it, so "what is being
   * asked" and "how to answer it" stay two separate texts.
   *
   * @param string $hint
   *   The hint (e.g. "Use arrows and Space to select").
   *
   * @return $this
   *   The builder.
   */
  public function hint(string $hint): self {
    $this->hint = $hint;

    return $this;
  }

  /**
   * Set the ghost text shown inside the editor while its buffer is empty.
   *
   * A placeholder never becomes a value: it disappears as soon as anything is
   * typed. Available on the text, number, textarea, password, suggest and
   * search types; any other type throws when the field is built.
   *
   * @param string $placeholder
   *   The ghost text (e.g. "E.g. Golden Beetroot").
   *
   * @return $this
   *   The builder.
   */
  public function placeholder(string $placeholder): self {
    $this->placeholder = $placeholder;

    return $this;
  }

  /**
   * Set the default value.
   *
   * @param mixed $default
   *   The default value, or a `fn (Context): mixed` closure computing a
   *   dynamic default from the run context. A closure that cannot be evaluated
   *   without answers reads as null in machine-readable output unless a
   *   {@see schemaDefault()} stands in for it.
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
    $this->hasSchemaDefault = TRUE;
    $this->schemaDefault = $default;

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
    $this->required = $required;
    $this->requiredMessage = $message;

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
   * @throws \DrevOps\Tui\Model\FormException
   *   When the field type does not support collecting several values.
   */
  public function multiple(bool $multiple = TRUE): self {
    if ($multiple && !$this->fieldType->supportsMultiple()) {
      throw new FormException(sprintf('Field "%s" of type "%s" does not collect several values; ->multiple() applies to select, search and file picker fields.', $this->id, $this->fieldType->value));
    }

    $this->multiple = $multiple;

    return $this;
  }

  /**
   * Offer a reveal/hide toggle in a password editor.
   *
   * @param bool $revealable
   *   Whether the toggle is enabled.
   *
   * @return $this
   *   The builder.
   */
  public function revealable(bool $revealable = TRUE): self {
    $this->revealable = $revealable;

    return $this;
  }

  /**
   * Prompt for a password twice and reject a mismatch before accepting.
   *
   * @param bool $confirm
   *   Whether confirmation mode is enabled.
   *
   * @return $this
   *   The builder.
   */
  public function confirmation(bool $confirm = TRUE): self {
    $this->confirm = $confirm;

    return $this;
  }

  /**
   * Allow the field to hand off to the user's $EDITOR.
   *
   * Honoured by the textarea widget: an available $EDITOR (or $VISUAL) can be
   * launched to compose the value, falling back to inline editing otherwise.
   *
   * @param bool $enabled
   *   Whether the external-editor handoff is offered.
   *
   * @return $this
   *   The builder.
   */
  public function externalEditor(bool $enabled = TRUE): self {
    $this->externalEditor = $enabled;

    return $this;
  }

  /**
   * Edit the field on its own full-screen editor rather than inline.
   *
   * A field is edited inline by default - its editor expands in place on the
   * panel when activated, and collapses back on accept or cancel. Declaring it
   * standalone opens that same editor full-screen instead: the better fit for a
   * widget that wants the whole viewport, such as a long option list, a month
   * calendar or a multi-line textarea.
   *
   * @param bool $standalone
   *   TRUE for the full-screen editor; FALSE to restore inline editing.
   *
   * @return $this
   *   The builder.
   */
  public function standalone(bool $standalone = TRUE): self {
    $this->render = $standalone ? RenderMode::Standalone : RenderMode::Inline;

    return $this;
  }

  /**
   * Note only: draw the card inside a themed border with minimal padding.
   *
   * @param bool $bordered
   *   Whether the note is boxed.
   *
   * @return $this
   *   The builder.
   */
  public function border(bool $bordered = TRUE): self {
    $this->bordered = $bordered;

    return $this;
  }

  /**
   * Note only: render a presentational table beneath the card's title and body.
   *
   * The cells carry the same `{{field}}` templating the title and body do, so a
   * table can reflect earlier answers. An empty header list renders the grid
   * with no header row, and ragged rows pad to the widest row's column count.
   *
   * @param list<string> $headers
   *   The header cells.
   * @param list<list<string>> $rows
   *   The body rows, each a list of cell strings.
   *
   * @return $this
   *   The builder.
   */
  public function table(array $headers, array $rows): self {
    $this->table = new TableSpec($headers, $rows);

    return $this;
  }

  /**
   * Number only: set the inclusive minimum accepted value.
   *
   * @param int $min
   *   The minimum.
   *
   * @return $this
   *   The builder.
   */
  public function min(int $min): self {
    $this->min = $min;

    return $this;
  }

  /**
   * Number only: set the inclusive maximum accepted value.
   *
   * @param int $max
   *   The maximum.
   *
   * @return $this
   *   The builder.
   */
  public function max(int $max): self {
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
   */
  public function step(int $step): self {
    $this->step = $step;

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
   */
  public function minSelections(int $min): self {
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
   */
  public function maxSelections(int $max): self {
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
   */
  public function startIn(string $directory): self {
    $this->pickerStart = $directory;

    return $this;
  }

  /**
   * File picker only: allow only files to be selected.
   *
   * Directories stay navigable so files beneath them remain reachable.
   *
   * @return $this
   *   The builder.
   */
  public function filesOnly(): self {
    $this->pickerMode = FilePickerMode::File;

    return $this;
  }

  /**
   * File picker only: allow only directories to be selected.
   *
   * @return $this
   *   The builder.
   */
  public function directoriesOnly(): self {
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
   */
  public function extensions(array $extensions): self {
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
   */
  public function showHidden(bool $show = TRUE): self {
    $this->pickerShowHidden = $show;

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
   * @throws \DrevOps\Tui\Model\FormException
   *   When the size is not positive.
   */
  public function maxSize(int $bytes): self {
    if ($bytes < 1) {
      throw new FormException(sprintf('Field "%s" declares a maximum file size of %d below one byte.', $this->id, $bytes));
    }

    $this->pickerMaxSize = $bytes;

    return $this;
  }

  /**
   * List widgets only: bound the visible option list to a page size.
   *
   * Longer lists page around the cursor rather than overflowing the viewport.
   * Honoured by the select, suggest, search, reorder and file picker widgets;
   * ignored by other types.
   *
   * @param int $size
   *   The number of option rows shown at once; must be positive.
   *
   * @return $this
   *   The builder.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the size is not positive.
   */
  public function pageSize(int $size): self {
    if ($size < 1) {
      throw new FormException(sprintf('Field "%s" declares a non-positive page size %d.', $this->id, $size));
    }

    $this->pageSize = $size;

    return $this;
  }

  /**
   * Date only: set the inclusive earliest selectable date.
   *
   * @param string $date
   *   The earliest date, as an ISO `Y-m-d` string.
   *
   * @return $this
   *   The builder.
   */
  public function minDate(string $date): self {
    $this->minDate = $date;

    return $this;
  }

  /**
   * Date only: set the inclusive latest selectable date.
   *
   * @param string $date
   *   The latest date, as an ISO `Y-m-d` string.
   *
   * @return $this
   *   The builder.
   */
  public function maxDate(string $date): self {
    $this->maxDate = $date;

    return $this;
  }

  /**
   * Date only: set the day the calendar week begins on.
   *
   * @param \DrevOps\Tui\Model\Weekday $weekday
   *   The week-start day.
   *
   * @return $this
   *   The builder.
   */
  public function weekStart(Weekday $weekday): self {
    $this->weekStart = $weekday;

    return $this;
  }

  /**
   * Set the conditional-visibility rule.
   *
   * @param \DrevOps\Tui\Condition\ConditionInterface $condition
   *   The condition gating the field, evaluated by the engine.
   *
   * @return $this
   *   The builder.
   */
  public function when(ConditionInterface $condition): self {
    $this->when = $condition;

    return $this;
  }

  /**
   * Set the derive rule.
   *
   * @param \DrevOps\Tui\Derive\Derive $derive
   *   The derive rule, evaluated by the engine.
   *
   * @return $this
   *   The builder.
   */
  public function derive(Derive $derive): self {
    $this->derive = $derive;

    return $this;
  }

  /**
   * Set the discovery rule.
   *
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure $discover
   *   The discovery rule - or a custom `fn (Context): mixed` detector -
   *   evaluated by the engine in update mode.
   *
   * @return $this
   *   The builder.
   */
  public function discover(DiscoverInterface|\Closure $discover): self {
    $this->discover = $discover;

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
    $this->validate = $validator;

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
    $this->transform = $transformer;

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
   */
  public function complete(array|\Closure $source): self {
    $this->completion = $source;

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
   */
  public function pattern(string $pattern): self {
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
   */
  public function slot(string $name, string $label = '', ?\Closure $validate = NULL): self {
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
   */
  public function option(string $value, string $label = '', string $description = '', bool $disabled = FALSE, string $disabled_reason = ''): self {
    $option = new Option($value, $label === '' ? $value : $label, $description, OptionKind::Option, $disabled, $disabled_reason);

    // Re-declaring a value replaces the earlier option in place, so the option
    // set stays unique; separators and headings carry no value and always
    // append.
    foreach ($this->options as $index => $existing) {
      if ($existing->kind === OptionKind::Option && $existing->value === $value) {
        $this->options[$index] = $option;

        return $this;
      }
    }

    $this->options[] = $option;

    return $this;
  }

  /**
   * Add a non-selectable separator row.
   *
   * @return $this
   *   The builder.
   */
  public function separator(): self {
    $this->options[] = new Option('', '', '', OptionKind::Separator);

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
   */
  public function heading(string $label): self {
    $this->options[] = new Option('', $label, '', OptionKind::Heading);

    return $this;
  }

  /**
   * Add several options from a value => label map, or a loader for them.
   *
   * @param array<array-key,string>|\Closure $options
   *   The options keyed by value with a label, or an
   *   `fn(): array<string,string>` that loads them on demand. A loader resolves
   *   lazily when the field's panel opens - showing a themed "Loading…" beside
   *   the field until it returns. A loader suits a field whose default is empty
   *   or explicit (select, search, suggest); a toggle or reorder derives its
   *   default from the options, so with a loader it should declare an explicit
   *   `->default()`.
   *
   * @return $this
   *   The builder.
   */
  public function options(array|\Closure $options): self {
    if ($options instanceof \Closure) {
      $this->optionsLoader = $options;

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
   * Repeats are free: a query already answered in this editor session is served
   * from a cache, and a burst of typing resolves once, not once per character.
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
   */
  public function optionsFrom(\Closure $source): self {
    $this->optionsSource = $source;

    return $this;
  }

  /**
   * Set the query length below which the query source is not called.
   *
   * A remote source asked for the empty query has to answer with everything, so
   * a floor keeps the field quiet until the query is worth sending. Below it
   * the field shows a prompt to keep typing instead of a list.
   *
   * @param int $length
   *   The minimum number of characters, at least one.
   *
   * @return $this
   *   The builder.
   */
  public function minQuery(int $length): self {
    if ($length < 1) {
      throw new FormException(sprintf('Field "%s" declares a minimum query length of %d; it must be at least one character (omit minQuery() to query on every keystroke).', $this->id, $length));
    }

    $this->queryMinLength = $length;

    return $this;
  }

  /**
   * Set the step count of a progress row, making its indicator a bar.
   *
   * Without a step count a progress row shows an indeterminate spinner; with
   * one it shows a bar that fills as the work advances through the steps.
   *
   * @param int $steps
   *   The number of steps.
   *
   * @return $this
   *   The builder.
   */
  public function steps(int $steps): self {
    if ($steps < 1) {
      throw new FormException(sprintf('Field "%s" declares %d progress steps; a determinate bar needs at least one step (omit steps() for a spinner).', $this->id, $steps));
    }

    $this->progressSteps = $steps;

    return $this;
  }

  /**
   * Set the work a progress row runs when activated.
   *
   * @param \Closure $work
   *   An `fn(\DrevOps\Tui\Primitive\ProgressReporter): void` that does the
   *   work, calling `advance(?string $label)` on the reporter once per step to
   *   move the bar and optionally set the trailing label.
   *
   * @return $this
   *   The builder.
   */
  public function run(\Closure $work): self {
    $this->progressWork = $work;

    return $this;
  }

  /**
   * Build the immutable Field.
   *
   * @return \DrevOps\Tui\Model\Field
   *   The field.
   */
  public function build(): Field {
    return new Field(
      $this->id,
      $this->label,
      $this->description,
      $this->fieldType,
      $this->resolveDefault(),
      $this->options,
      $this->required,
      $this->requiredMessage,
      $this->when,
      $this->derive,
      $this->discover,
      $this->validate,
      $this->transform,
      $this->revealable,
      $this->confirm,
      $this->externalEditor,
      $this->buildBounds(),
      $this->buildPickerConstraints(),
      $this->pickerStart,
      $this->pickerShowHidden,
      $this->pageSize,
      $this->completion,
      $this->buildDateBounds(),
      $this->render,
      $this->multiple,
      $this->bordered,
      $this->buildSelectionBounds(),
      $this->optionsLoader,
      $this->progressSteps,
      $this->progressWork,
      schemaDefault: $this->schemaDefault,
      hasSchemaDefault: $this->hasSchemaDefault,
      table: $this->table,
      template: $this->buildTemplate(),
      optionsSource: $this->optionsSource,
      queryMinLength: $this->queryMinLength,
      hint: $this->hint,
      placeholder: $this->placeholder,
    );
  }

  /**
   * Assemble the template of a template field from the declared pattern.
   *
   * @return \DrevOps\Tui\Model\Template|null
   *   The template, or NULL for any other field type or an absent pattern.
   *
   * @throws \DrevOps\Tui\Model\FormException
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
    if ($this->multiple) {
      return [];
    }

    // A toggle is always in one of its two states, so it defaults to the first
    // option's value rather than an empty value that would not match either.
    // The value is read off the option, not its array key, so a numeric-string
    // value like "0" is not coerced to an int.
    if ($this->fieldType === FieldType::Toggle && $this->options !== []) {
      return reset($this->options)->value;
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
    $values = [];
    foreach ($this->options as $option) {
      if ($option->selectable()) {
        $values[] = $option->value;
      }
    }

    $desired = $this->hasDefault ? Field::stringList($this->default) : [];

    return Field::canonicalOrder($values, $desired);
  }

  /**
   * Assemble the number bounds from the declared min/max/step, if any.
   *
   * @return \DrevOps\Tui\Model\NumberBounds|null
   *   The bounds, or NULL when none were declared.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When min exceeds max, or the step is not positive.
   */
  protected function buildBounds(): ?NumberBounds {
    if ($this->min === NULL && $this->max === NULL && $this->step === NULL) {
      return NULL;
    }

    if ($this->min !== NULL && $this->max !== NULL && $this->min > $this->max) {
      throw new FormException(sprintf('Field "%s" declares min %d greater than max %d.', $this->id, $this->min, $this->max));
    }

    if ($this->step !== NULL && $this->step < 1) {
      throw new FormException(sprintf('Field "%s" declares a non-positive step %d.', $this->id, $this->step));
    }

    return new NumberBounds($this->min, $this->max, $this->step);
  }

  /**
   * Assemble the selection bounds from the declared min/max selections, if any.
   *
   * @return \DrevOps\Tui\Model\SelectionBounds|null
   *   The bounds, or NULL when no selection limit was declared.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When selection limits are declared on a non-multiple field, or the
   *   minimum exceeds the maximum.
   */
  protected function buildSelectionBounds(): ?SelectionBounds {
    if ($this->minSelections === NULL && $this->maxSelections === NULL) {
      return NULL;
    }

    if (!$this->multiple) {
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
   * @return \DrevOps\Tui\Model\FilePickerConstraints
   *   The constraints; unconstrained when nothing was declared.
   */
  protected function buildPickerConstraints(): FilePickerConstraints {
    return new FilePickerConstraints($this->pickerMode, $this->pickerExtensions, $this->pickerMaxSize);
  }

  /**
   * Assemble the date bounds for a date field from the declared min/max/start.
   *
   * @return \DrevOps\Tui\Model\DateBounds|null
   *   The bounds for a date field, or NULL for any other field type.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When a declared date is not a valid `Y-m-d` date, or min is after max.
   */
  protected function buildDateBounds(): ?DateBounds {
    if ($this->fieldType !== FieldType::Calendar) {
      return NULL;
    }

    $min = $this->parseBoundDate($this->minDate);
    $max = $this->parseBoundDate($this->maxDate);

    if ($min instanceof \DateTimeImmutable && $max instanceof \DateTimeImmutable && $min > $max) {
      throw new FormException(sprintf('Field "%s" declares min date %s after max date %s.', $this->id, $min->format('Y-m-d'), $max->format('Y-m-d')));
    }

    return new DateBounds($min, $max, $this->weekStart ?? Weekday::Monday);
  }

  /**
   * Strictly parse a declared bound date, failing loudly on a bad value.
   *
   * @param string|null $value
   *   The declared date string, or NULL when the bound is open.
   *
   * @return \DateTimeImmutable|null
   *   The parsed date, or NULL when none was declared.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When the value is not a valid `Y-m-d` date.
   */
  protected function parseBoundDate(?string $value): ?\DateTimeImmutable {
    if ($value === NULL) {
      return NULL;
    }

    $date = DateBounds::parse($value);
    if (!$date instanceof \DateTimeImmutable) {
      throw new FormException(sprintf('Field "%s" declares an invalid date "%s".', $this->id, $value));
    }

    return $date;
  }

  /**
   * The engine default for a field type when none is declared.
   *
   * @param \DrevOps\Tui\Model\FieldType $type
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
