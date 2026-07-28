<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * A single question in the configuration model.
 *
 * The definition is immutable except for three resolved concerns written back:
 * options a field declares indirectly - through a loader resolved once
 * (`$optionsLoader`) or a resolver re-run as the answers change
 * (`$optionsResolver`) - land in `$options`, a progress row tracks its live
 * indicator (`$progressCurrent`, `$progressLabel`) as its work advances, and
 * the owning form definition stamps the field's place in the condition graph
 * (`$conditionalDepth`). Those five properties are the only mutable state.
 *
 * @package DrevOps\Tui\Model
 */
final class Field {

  /**
   * The shape a portable environment variable name has.
   *
   * Anything outside it cannot be set portably from a shell, so a name that
   * does not match would be declared but unreachable.
   */
  protected const string ENV_NAME_PATTERN = '/^[A-Za-z_]\w*$/';

  /**
   * The option rows for choice-based fields, in display order.
   *
   * @var list<\DrevOps\Tui\Model\Option>
   */
  public array $options;

  /**
   * How many conditions deep the field sits, resolved by its form definition.
   *
   * Zero for a field that shows unconditionally, one for a field whose `when`
   * rule references only unconditional fields, and one more for each further
   * link in the chain. A field outside any form definition keeps the zero it
   * is constructed with.
   *
   * @see \DrevOps\Tui\Model\FormDefinition::resolveConditionalDepths()
   */
  public int $conditionalDepth = 0;

  /**
   * File picker only: the type, extension and size limits on a valid pick.
   */
  public readonly FilePickerConstraints $pickerConstraints;

  /**
   * Construct a field.
   *
   * @param string $id
   *   The unique field id.
   * @param string $label
   *   The human-readable label.
   * @param string $description
   *   The help text.
   * @param \DrevOps\Tui\Model\FieldType $type
   *   The widget type.
   * @param mixed $default
   *   The declared default value, or a `fn (Context): mixed` closure computing
   *   a dynamic default from the run context.
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   Option rows for choice-based fields, in display order - a list of
   *   {@see Option} rows or the value => label shorthand map (normalized via
   *   {@see Option::list()}).
   * @param bool $required
   *   Whether a value is required.
   * @param string $requiredMessage
   *   The message shown when a required field is left empty; empty derives one
   *   from the label.
   * @param \DrevOps\Tui\Condition\ConditionInterface|null $when
   *   The conditional-visibility rule, evaluated by the engine.
   * @param \DrevOps\Tui\Derive\Derive|null $derive
   *   The derive rule, evaluated by the engine.
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure|null $discover
   *   The discovery rule - or a custom `fn (Context): mixed` detector -
   *   evaluated by the engine in update mode.
   * @param \Closure|null $validate
   *   A declared validator `fn (mixed $value): ?string` returning an error
   *   message, or NULL when the value is valid.
   * @param \Closure|null $transform
   *   A declared transformer `fn (mixed $value): mixed` normalizing an
   *   accepted value.
   * @param bool $revealable
   *   Password only: whether the editor offers a reveal/hide toggle.
   * @param bool $confirm
   *   Password only: whether the editor prompts for the value twice and rejects
   *   a mismatch before accepting.
   * @param bool $externalEditor
   *   Whether the field may hand off to the user's $EDITOR for composing its
   *   value. Honoured by the textarea widget; ignored by other types.
   * @param \DrevOps\Tui\Model\NumberBounds|null $bounds
   *   Number only: optional min/max/step bounds; NULL for a plain integer
   *   entry with no range or keyboard stepping.
   * @param \DrevOps\Tui\Model\FilePickerConstraints|null $picker_constraints
   *   File picker only: the type, extension and size limits enforced on a pick;
   *   NULL (or the default) leaves the picker unconstrained. Ignored by other
   *   types.
   * @param string $pickerStart
   *   File picker only: the directory the browser opens at and cannot ascend
   *   above; empty falls back to the current working directory.
   * @param bool $pickerShowHidden
   *   File picker only: whether dot-entries are shown when the browser opens.
   * @param int|null $pageSize
   *   Choice widgets only: how many option rows show at once before the list
   *   pages; NULL uses the widget default. A purely visual bound - it does not
   *   constrain a headless value, so it is absent from the machine schema.
   * @param list<string>|\Closure $completion
   *   Text only: the inline ghost-text completion source - a list of candidate
   *   strings, or a `fn (array<string,mixed> $answers): list<string>` closure
   *   over the answers collected so far. Empty disables ghost-text; ignored by
   *   other types.
   * @param \DrevOps\Tui\Model\DateBounds|null $dateBounds
   *   Date only: the min/max range and week-start day; NULL for non-date
   *   fields.
   * @param \DrevOps\Tui\Model\RenderMode $render
   *   Where the field's editor is drawn: inline in the panel (the default) or
   *   full-screen on its own standalone editor.
   * @param bool $multiple
   *   Whether the field collects several values as a list rather than one;
   *   honoured by the select, search and file picker types.
   * @param bool $bordered
   *   Note only: whether the card is drawn inside a themed border with minimal
   *   padding; ignored by other types.
   * @param \DrevOps\Tui\Model\SelectionBounds|null $selectionBounds
   *   Multiple only: optional minimum/maximum selection counts; NULL for no
   *   count limit.
   * @param \Closure|null $optionsLoader
   *   An `fn(): array<string,string>` loading the options on demand, or NULL
   *   for static options. Resolved lazily when the panel opens (headless
   *   collection resolves it up front); until then the field reads as loading.
   * @param int|null $progressSteps
   *   Progress only: the number of steps for a determinate bar, or NULL for an
   *   indeterminate spinner.
   * @param \Closure|null $progressWork
   *   Progress only: an `fn(\DrevOps\Tui\Primitive\ProgressReporter): void` run
   *   when the row is activated, driving the indicator through `advance()`.
   * @param int|null $progressCurrent
   *   Progress only: the live step count while the work runs - the bar fill, or
   *   the spinner tick, or NULL before it runs; mutated as the work advances.
   * @param string $progressLabel
   *   Progress only: the trailing label the work sets through `advance()`,
   *   shown after the bar or spinner glyph. Mutated as the work advances.
   * @param mixed $schemaDefault
   *   A static value standing in for {@see $default} in machine-readable output
   *   when the declared default is a closure that cannot be resolved without
   *   answers; consulted only for a closure default and only when
   *   {@see $hasSchemaDefault} is TRUE.
   * @param bool $hasSchemaDefault
   *   Whether a {@see $schemaDefault} was declared, so a declared NULL is
   *   distinguishable from an absent one.
   * @param \DrevOps\Tui\Model\TableSpec|null $table
   *   Note only: a presentational table rendered beneath the card's title and
   *   body; NULL when the note carries no table. Ignored by other types.
   * @param \DrevOps\Tui\Model\Template|null $template
   *   Template only: the fixed shape whose `{{placeholder}}` slots the field
   *   fills in. NULL for every other type.
   * @param \Closure|null $optionsSource
   *   An
   *   `fn(string $query, array<string,mixed> $answers): array<string,string>`
   *   resolving the options for a live query, or NULL for options that do not
   *   follow the query. Unlike a loader it is called again whenever the query
   *   changes, so the candidates can come from a remote backend that filters
   *   for itself.
   * @param int $queryMinLength
   *   The number of query characters below which a query source is not called
   *   at all, so a remote backend is not asked to list everything; zero calls
   *   it for the empty query too.
   * @param string $hint
   *   How to answer the question (e.g. "Use arrows and Space to select"), shown
   *   beneath the description and styled apart from it. Empty shows no hint.
   * @param string $placeholder
   *   The ghost text shown inside the editor while its buffer is empty (e.g.
   *   "E.g. Golden Beetroot"). Never becomes a value: it disappears as soon as
   *   anything is typed, and it is suppressed without colour, where it could
   *   not be told apart from a real entry.
   * @param string $envName
   *   The environment variable that answers the field, replacing the
   *   mechanically prefixed one. Absolute - the form's prefix is not applied to
   *   it - so an existing published name can be reproduced exactly; empty keeps
   *   the mechanical name.
   * @param list<string> $envAliases
   *   Further environment variables the field also answers to, absolute and in
   *   precedence order, so a naming scheme can change without a breaking
   *   cut-over. Consulted only when none of the names before them is set.
   * @param bool $ghost
   *   Suggest only: whether the leading prefix match among the field's options
   *   is previewed as inline ghost-text after the caret, in the same slot the
   *   placeholder uses while nothing is typed. A purely visual aid over the
   *   same option set, so it is absent from the machine schema and leaves a
   *   headless collection untouched.
   * @param array<int,string> $ratingCaptions
   *   Rating only: the caption of a point on the scale, keyed by the point. The
   *   scale is the range in {@see $bounds}; a caption is decoration over it, so
   *   points may be captioned sparsely and an uncaptioned point still answers.
   * @param \Closure|null $optionsResolver
   *   An `fn(Context $context): array<string,string>` resolving the options
   *   from the answers collected so far, or NULL for options that do not follow
   *   them. Unlike a loader it is called again whenever the answers change, so
   *   one field's choices can narrow by another's answer.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
    public readonly string $description,
    public readonly FieldType $type,
    public readonly mixed $default,
    array $options = [],
    public readonly bool $required = FALSE,
    public readonly string $requiredMessage = '',
    public readonly ?ConditionInterface $when = NULL,
    public readonly ?Derive $derive = NULL,
    public readonly DiscoverInterface|\Closure|null $discover = NULL,
    public readonly ?\Closure $validate = NULL,
    public readonly ?\Closure $transform = NULL,
    public readonly bool $revealable = FALSE,
    public readonly bool $confirm = FALSE,
    public readonly bool $externalEditor = FALSE,
    public readonly ?NumberBounds $bounds = NULL,
    ?FilePickerConstraints $picker_constraints = NULL,
    public readonly string $pickerStart = '',
    public readonly bool $pickerShowHidden = FALSE,
    public readonly ?int $pageSize = NULL,
    public readonly array|\Closure $completion = [],
    public readonly ?DateBounds $dateBounds = NULL,
    public readonly RenderMode $render = RenderMode::Inline,
    public readonly bool $multiple = FALSE,
    public readonly bool $bordered = FALSE,
    public readonly ?SelectionBounds $selectionBounds = NULL,
    public ?\Closure $optionsLoader = NULL,
    public readonly ?int $progressSteps = NULL,
    public readonly ?\Closure $progressWork = NULL,
    public ?int $progressCurrent = NULL,
    public string $progressLabel = '',
    public readonly mixed $schemaDefault = NULL,
    public readonly bool $hasSchemaDefault = FALSE,
    public readonly ?TableSpec $table = NULL,
    public readonly ?Template $template = NULL,
    public readonly ?\Closure $optionsSource = NULL,
    public readonly int $queryMinLength = 0,
    public readonly string $hint = '',
    public readonly string $placeholder = '',
    public readonly string $envName = '',
    public readonly array $envAliases = [],
    public readonly bool $ghost = FALSE,
    public readonly array $ratingCaptions = [],
    public readonly ?\Closure $optionsResolver = NULL,
  ) {
    $this->assertEnvNames();
    $this->assertRatingCaptions();

    if ($this->type === FieldType::Template && !$this->template instanceof Template) {
      throw new FormException(sprintf('Field "%s" is a template field but declares no pattern; add ->pattern() with the shape to fill in.', $this->id));
    }

    if ($this->optionsSource instanceof \Closure) {
      if (!$this->type->supportsQuerySource()) {
        throw new FormException(sprintf('Field "%s" of type "%s" cannot source its options from a query; only search and suggest fields show one.', $this->id, $this->type->value));
      }

      if ($options !== [] || $optionsLoader instanceof \Closure) {
        throw new FormException(sprintf('Field "%s" declares both a query source and its own options; a query source replaces them, so declare only one.', $this->id));
      }
    }

    if ($this->queryMinLength > 0 && !$this->optionsSource instanceof \Closure) {
      throw new FormException(sprintf('Field "%s" declares a minimum query length but no query source to apply it to.', $this->id));
    }

    if (($options !== [] || $optionsLoader instanceof \Closure || $this->optionsResolver instanceof \Closure) && !$this->type->supportsOptions()) {
      throw new FormException(sprintf('Field "%s" of type "%s" shows no options; only select, search, suggest, toggle and reorder fields have a list.', $this->id, $this->type->value));
    }

    if ($this->optionsResolver instanceof \Closure) {
      if ($options !== [] || $optionsLoader instanceof \Closure || $this->optionsSource instanceof \Closure) {
        throw new FormException(sprintf('Field "%s" resolves its options from the answers and declares another set of options as well; the resolved set replaces them, so declare only one.', $this->id));
      }
    }

    if ($this->placeholder !== '' && !$this->type->supportsPlaceholder()) {
      throw new FormException(sprintf('Field "%s" of type "%s" shows no placeholder; only text, number, textarea, password, suggest and search fields have an input buffer to ghost.', $this->id, $this->type->value));
    }

    if ($this->multiple && !$this->type->supportsMultiple()) {
      throw new FormException(sprintf('Field "%s" of type "%s" does not collect several values; only select, search and file picker fields may be multiple.', $this->id, $this->type->value));
    }

    if ($this->selectionBounds instanceof SelectionBounds && !$this->multiple) {
      throw new FormException(sprintf('Field "%s" declares selection limits but does not collect several values.', $this->id));
    }

    $this->options = Option::list($options);
    $this->pickerConstraints = $picker_constraints ?? new FilePickerConstraints();
  }

  /**
   * Reject declared environment variable names that cannot be honoured.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When a declared name is not portable, or an alias repeats the name it
   *   would never be reached behind.
   */
  protected function assertEnvNames(): void {
    if ($this->envName !== '' && preg_match(self::ENV_NAME_PATTERN, $this->envName) !== 1) {
      throw new FormException(sprintf('Field "%s" declares the environment variable name "%s", which is not a portable name; use letters, digits and underscores, starting with a letter or underscore.', $this->id, $this->envName));
    }

    $seen = [];

    foreach ($this->envAliases as $env_alias) {
      if (preg_match(self::ENV_NAME_PATTERN, $env_alias) !== 1) {
        throw new FormException(sprintf('Field "%s" declares the environment variable alias "%s", which is not a portable name; use letters, digits and underscores, starting with a letter or underscore.', $this->id, $env_alias));
      }

      if ($env_alias === $this->envName) {
        throw new FormException(sprintf('Field "%s" declares "%s" as both its environment variable name and an alias of it; the alias would never be reached, so declare it once.', $this->id, $env_alias));
      }

      if (isset($seen[$env_alias])) {
        throw new FormException(sprintf('Field "%s" declares the environment variable alias "%s" more than once; only the first would ever be reached.', $this->id, $env_alias));
      }

      $seen[$env_alias] = TRUE;
    }
  }

  /**
   * Reject captions that no point on the scale would ever show.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When captions are declared on a field that draws no scale, or a caption
   *   is keyed outside the scale's range.
   */
  protected function assertRatingCaptions(): void {
    if ($this->ratingCaptions === []) {
      return;
    }

    if ($this->type !== FieldType::Rating) {
      throw new FormException(sprintf('Field "%s" of type "%s" draws no scale to caption; ->captions() applies to rating fields.', $this->id, $this->type->value));
    }

    foreach (array_keys($this->ratingCaptions) as $point) {
      if ($this->bounds instanceof NumberBounds && !$this->bounds->contains($point)) {
        throw new FormException(sprintf('Field "%s" captions the point %d, which is outside its scale of %s.', $this->id, $point, $this->bounds->describe()));
      }
    }
  }

  /**
   * Whether the field collects a list of values rather than a single value.
   *
   * @return bool
   *   TRUE for a reorder field and for any multiple choice or file picker.
   */
  public function collectsList(): bool {
    return $this->multiple || $this->type === FieldType::Reorder;
  }

  /**
   * Whether the field is a multi-selection over its declared option set.
   *
   * Narrower than {@see collectsList()}: a multiple file picker collects a
   * list too, but its entries come from the filesystem, not the options.
   *
   * @return bool
   *   TRUE for the option-backed multi-choice fields.
   */
  public function isMultiChoice(): bool {
    return $this->type === FieldType::Reorder || ($this->multiple && $this->type->constrainsToOptions());
  }

  /**
   * Whether a headless value has the shape this field collects.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return bool
   *   TRUE when the value's type matches the field.
   */
  public function acceptsValue(mixed $value): bool {
    return match (TRUE) {
      $this->type === FieldType::Confirm, $this->type === FieldType::Pause => is_bool($value),
      $this->collectsList() => is_array($value),
      // A scale has no point between its points, so only a whole number names
      // one - where a number field takes any numeric entry and rounds it.
      $this->type === FieldType::Rating => is_int($value),
      $this->type->collectsInteger() => is_int($value) || is_float($value),
      // An empty string is an unset date, left to the required check; any
      // other value must be a strict `Y-m-d` calendar date.
      $this->type === FieldType::Calendar => is_string($value) && ($value === '' || DateBounds::parse($value) instanceof \DateTimeImmutable),
      default => is_string($value),
    };
  }

  /**
   * The human name of the value shape this field collects, translated.
   *
   * @return string
   *   The value-kind fragment (e.g. "a string", "a list").
   */
  public function valueKind(): string {
    return match (TRUE) {
      $this->type === FieldType::Confirm, $this->type === FieldType::Pause => Translator::t('a boolean'),
      $this->collectsList() => Translator::t('a list'),
      $this->type === FieldType::Rating => Translator::t('a whole number'),
      $this->type->collectsInteger() => Translator::t('a number'),
      $this->type === FieldType::Calendar => Translator::t('a date (YYYY-MM-DD)'),
      default => Translator::t('a string'),
    };
  }

  /**
   * Get a selectable-or-disabled option by its value.
   *
   * Structural rows (separators, headings) carry no value and are never
   * returned.
   */
  public function option(string $value): ?Option {
    foreach ($this->options as $option) {
      if ($option->kind === OptionKind::Option && $option->value === $value) {
        return $option;
      }
    }

    return NULL;
  }

  /**
   * The values of the selectable options, in display order.
   *
   * @return list<string>
   *   The selectable option values (excludes separators, headings and disabled
   *   options).
   */
  public function selectableValues(): array {
    return Option::selectableValues($this->options);
  }

  /**
   * Whether the field's option rows stand as declared.
   *
   * @return bool
   *   FALSE while a loader, a resolver or a query source still owes the field
   *   its rows, so there is nothing yet to count or to check a default against.
   */
  public function hasSettledOptions(): bool {
    return !$this->optionsLoader instanceof \Closure && !$this->optionsResolver instanceof \Closure && !$this->optionsSource instanceof \Closure;
  }

  /**
   * Whether the field's option set follows the answers rather than standing.
   *
   * @return bool
   *   TRUE when the options are resolved from the collected answers or from a
   *   live query, so no one list describes the field.
   */
  public function hasDynamicOptions(): bool {
    return $this->optionsResolver instanceof \Closure || $this->optionsSource instanceof \Closure;
  }

  /**
   * A value restated against the option set as it now stands.
   *
   * A choice value outlives the options it was picked from: a set resolved
   * from the answers narrows as they change, leaving a value that is no longer
   * offered, a ranking that no longer covers the set, or a toggle sitting on a
   * state that is gone. This drops what the set no longer holds, completes a
   * ranking back to a full permutation and returns a toggle to its first
   * state, so a value always describes the options in front of it.
   *
   * A suggest field's options are hints rather than a closed set, so its value
   * is never reconciled against them.
   *
   * @param mixed $value
   *   The current value.
   *
   * @return mixed
   *   The value the current options can carry.
   */
  public function reconcileValue(mixed $value): mixed {
    if (!$this->type->supportsOptions() || $this->type === FieldType::Suggest) {
      return $value;
    }

    $selectable = $this->selectableValues();

    if ($this->type === FieldType::Reorder) {
      return self::canonicalOrder($selectable, self::stringList($value));
    }

    if ($this->isMultiChoice()) {
      return array_values(array_filter(self::stringList($value), static fn(string $item): bool => in_array($item, $selectable, TRUE)));
    }

    $current = is_scalar($value) ? (string) $value : '';

    if (in_array($current, $selectable, TRUE)) {
      return $current;
    }

    // A toggle is always in one of its states, so a value the set no longer
    // offers falls back to the first option rather than to nothing.
    return $this->type === FieldType::Toggle ? ($selectable[0] ?? '') : '';
  }

  /**
   * The whole message for an empty value on a required field, else NULL.
   *
   * Unlike the neighbouring checks this returns a complete sentence rather than
   * a fragment: the message is the consumer's to declare, so it cannot be
   * framed by a caller.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The declared message, or one derived from the label; NULL when the field
   *   is optional or the value is not empty.
   */
  public function requiredViolation(mixed $value): ?string {
    // Strict comparison, so a FALSE confirm and a 0 number are values, not
    // omissions.
    if (!$this->required || !in_array($value, ['', [], NULL], TRUE)) {
      return NULL;
    }

    if ($this->requiredMessage !== '') {
      return Translator::t($this->requiredMessage);
    }

    return Translator::t('@label is required.', ['@label' => Translator::t($this->label)]);
  }

  /**
   * The first violated number, date or selection-count bound, as a fragment.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The violation fragment (e.g. "between 1 and 10", "at least 2 items"), or
   *   NULL when the value is in range or the field declares no bounds.
   */
  public function boundsViolation(mixed $value): ?string {
    return $this->bounds?->violation($value) ?? $this->dateBounds?->violation($value) ?? $this->selectionBounds?->violation($value);
  }

  /**
   * The file picker limit a supplied path violates, as a fragment, else NULL.
   *
   * @param mixed $value
   *   The candidate value - a path, or a list of paths in multiple mode.
   *
   * @return string|null
   *   The violation fragment (e.g. "an existing file"), or NULL when the path
   *   meets every limit or the field declares no picker constraints.
   */
  public function pickerViolation(mixed $value): ?string {
    if ($this->type !== FieldType::FilePicker) {
      return NULL;
    }

    return $this->pickerConstraints->violation($value);
  }

  /**
   * The reason a supplied value does not fit the field's template, else NULL.
   *
   * An empty string is an unfilled template, left to the required check; any
   * other value must have the template's shape and pass every slot validator.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The error message, or NULL when the value fits or the field declares no
   *   template.
   */
  public function templateError(mixed $value): ?string {
    if (!$this->template instanceof Template || !is_string($value) || $value === '') {
      return NULL;
    }

    return $this->template->error($value);
  }

  /**
   * The values of the template's slots recovered from an assembled answer.
   *
   * @param mixed $value
   *   The assembled answer.
   *
   * @return array<string,string>
   *   The value of each slot keyed by slot name; empty when the field declares
   *   no template or the answer does not have its shape.
   */
  public function templateParts(mixed $value): array {
    if (!$this->template instanceof Template || !is_string($value)) {
      return [];
    }

    return $this->template->extract($value);
  }

  /**
   * Validate a supplied value against the field's selectable options.
   *
   * Handles both a single-choice scalar and a multi-choice list, returning the
   * first offending item. The message is a caller-agnostic fragment so the
   * engine and the schema validator can each frame it their own way.
   *
   * @param mixed $value
   *   The candidate value - a scalar for single-choice, a list for multi.
   *
   * @return string|null
   *   An error fragment when an item is not a selectable option, or NULL when
   *   the field is unconstrained or every item is allowed.
   */
  public function optionError(mixed $value): ?string {
    // A field that declares no options constrains nothing - but one whose
    // options follow a query or the answers is constrained by whatever they
    // resolved to, and resolving to nothing means the value does not exist.
    if (!$this->type->constrainsToOptions() || ($this->options === [] && !$this->hasDynamicOptions())) {
      return NULL;
    }

    if ($this->isMultiChoice()) {
      if (!is_array($value)) {
        return Translator::t('value must be a list');
      }

      $items = $value;
    }
    else {
      $items = [$value];
    }

    foreach ($items as $item) {
      $error = $this->scalarOptionError(is_scalar($item) ? (string) $item : '');
      if ($error !== NULL) {
        return $error;
      }
    }

    if ($this->type === FieldType::Reorder) {
      return $this->rankingError($items);
    }

    return NULL;
  }

  /**
   * Classify a single scalar value against the selectable options.
   *
   * @param string $value
   *   The candidate value.
   *
   * @return string|null
   *   A fragment naming the value when it is disabled or unknown, or NULL when
   *   it is a selectable option.
   */
  protected function scalarOptionError(string $value): ?string {
    if (in_array($value, $this->selectableValues(), TRUE)) {
      return NULL;
    }

    // Listing the allowed values is what makes the message useful, so when a
    // query source found nothing there is no list to offer and naming the value
    // is all that can honestly be said.
    if ($this->options === []) {
      return Translator::t('value "@value" was not found', ['@value' => $value]);
    }

    $option = $this->option($value);
    if ($option instanceof Option && $option->disabled) {
      return $option->disabledReason !== ''
        ? Translator::t('option "@value" is disabled: @reason', [
          '@value' => $value,
          '@reason' => $option->disabledReason,
        ])
        : Translator::t('option "@value" is disabled', ['@value' => $value]);
    }

    return Translator::t('value "@value" is not one of: @options', [
      '@value' => $value,
      '@options' => implode(', ', $this->selectableValues()),
    ]);
  }

  /**
   * Check that a ranking lists every selectable option exactly once.
   *
   * Membership is verified by the caller, so a value that is a full
   * permutation has the same length as the option set with no repeats.
   *
   * @param array<array-key,mixed> $items
   *   The supplied ranking, already confirmed to hold only selectable values.
   *
   * @return string|null
   *   An error fragment when the ranking omits or repeats an option, or NULL
   *   when it is a complete permutation.
   */
  protected function rankingError(array $items): ?string {
    $selectable = $this->selectableValues();

    $seen = [];
    foreach ($items as $item) {
      $seen[is_scalar($item) ? (string) $item : ''] = TRUE;
    }

    if (count($items) === count($selectable) && count($seen) === count($items)) {
      return NULL;
    }

    return Translator::t('must rank every option exactly once (@options)', ['@options' => implode(', ', $selectable)]);
  }

  /**
   * Coerce a value to a list of strings, dropping every non-string item.
   *
   * @param mixed $value
   *   The value.
   *
   * @return list<string>
   *   The string items, in order; empty when the value is not an array.
   */
  public static function stringList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $out = [];

    foreach ($value as $item) {
      if (is_string($item)) {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * Order a set of values, completing and de-duplicating a desired ordering.
   *
   * The desired values that belong to the allowed set come first - in the
   * given order, de-duplicated - then every allowed value the desired list
   * omits is appended in its declared order. The result is always a full
   * permutation of the allowed values, so a partial or dirty ordering still
   * resolves to a complete one.
   *
   * @param list<string> $allowed
   *   The full set of values, in declared order.
   * @param list<string> $desired
   *   The requested ordering; values outside the allowed set are ignored and
   *   repeats collapsed.
   *
   * @return list<string>
   *   The allowed values in the resolved order.
   */
  public static function canonicalOrder(array $allowed, array $desired): array {
    $set = array_fill_keys($allowed, TRUE);

    $order = [];
    $seen = [];
    foreach ($desired as $value) {
      if (isset($set[$value]) && !isset($seen[$value])) {
        $order[] = $value;
        $seen[$value] = TRUE;
      }
    }

    foreach ($allowed as $value) {
      if (!isset($seen[$value])) {
        $order[] = $value;
        $seen[$value] = TRUE;
      }
    }

    return $order;
  }

}
