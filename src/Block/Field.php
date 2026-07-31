<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\BindCapableTrait;
use DrevOps\Tui\Block\Capability\CaptureCapableInterface;
use DrevOps\Tui\Block\Capability\CollectCapableInterface;
use DrevOps\Tui\Block\Capability\ConstrainCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Capability\RejectCapableInterface;
use DrevOps\Tui\Block\Element\FieldElementsInterface;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Field\FieldFactory;
use DrevOps\Tui\Field\FieldInterface;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * The block that collects.
 *
 * It is the only kind that contributes to the collected result, and the only
 * kind that opens in place to capture one. One field owns both of its modes:
 * in view mode it is one line carrying the answer, and opening it hands the
 * region right of its label over to collecting a new one.
 *
 * A draft is what you are typing and a value is what was accepted, which is why
 * closing a field without accepting leaves the answer where it was.
 *
 * One object carries both what the field was declared to be and what it is
 * holding right now. The declaration is grouped by the capability it belongs
 * to: what is owed and what shape it has are Collect's, what a value is
 * measured against is Constrain's, and what makes the field appear at all is
 * Depend's. What is left over - the text around the row, and the per-kind
 * switches a single kind of editor honours - is the field's own.
 *
 * @package DrevOps\Tui\Block
 */
final class Field extends AbstractBlock implements
  BindCapableInterface,
  CaptureCapableInterface,
  CollectCapableInterface,
  ConstrainCapableInterface,
  DependCapableInterface,
  FocusCapableInterface,
  RejectCapableInterface {

  use BindCapableTrait;
  use DependCapableTrait;
  use FocusCapableTrait;

  /**
   * The shape a portable environment variable name has.
   *
   * Anything outside it cannot be set portably from a shell, so a name that
   * does not match would be declared but unreachable.
   */
  protected const string ENV_NAME_PATTERN = '/^[A-Za-z_]\w*$/';

  /**
   * Which of its two shapes it is drawing.
   */
  protected Mode $mode = Mode::View;

  /**
   * The accepted answer, or NULL until one is.
   */
  protected mixed $value = NULL;

  /**
   * What is being typed, before it is accepted.
   */
  protected mixed $draft = NULL;

  /**
   * What the field opened onto, for as long as it is open.
   */
  protected ?FieldInterface $editor = NULL;

  /**
   * The rows edit mode opens onto, in the order they were declared.
   *
   * @var list<\DrevOps\Tui\Model\Option>
   */
  protected array $entries = [];

  /**
   * What owes the field one set of rows, resolved once.
   */
  protected ?\Closure $loader = NULL;

  /**
   * What resolves the rows from the answers, run again as they change.
   */
  protected ?\Closure $resolver = NULL;

  /**
   * What resolves the rows from a live query, run again as it changes.
   */
  protected ?\Closure $source = NULL;

  /**
   * The query length below which a query source is not called at all.
   */
  protected int $queryMinLength = 0;

  /**
   * What this field will accept, stated before anything is refused.
   */
  protected ?string $constraint = NULL;

  /**
   * Why the last value was refused, until one is acceptable again.
   */
  protected ?string $refusal = NULL;

  /**
   * The explanatory text drawn under the field while it is open.
   */
  protected string $description = '';

  /**
   * The long-form text behind its help key.
   */
  protected string $help = '';

  /**
   * The ghost text shown in the editor while its buffer is empty.
   */
  protected string $placeholder = '';

  /**
   * Whether an answer is owed.
   */
  protected bool $required = FALSE;

  /**
   * What is said when a required answer is missing.
   */
  protected string $requiredMessage = '';

  /**
   * What refuses a value, and says why.
   *
   * @var \Closure(mixed): ?string|null
   */
  protected ?\Closure $validate = NULL;

  /**
   * What normalizes an accepted value before it is held.
   *
   * @var \Closure(mixed): mixed|null
   */
  protected ?\Closure $transform = NULL;

  /**
   * What computes this field's answer from the others, or NULL for none.
   */
  protected ?Derive $derive = NULL;

  /**
   * What detects an existing answer outside the form, or NULL for none.
   */
  protected DiscoverInterface|\Closure|null $discover = NULL;

  /**
   * How large a number may be, or NULL when its magnitude is unbounded.
   */
  protected ?NumberBounds $bounds = NULL;

  /**
   * How early or late a date may be, or NULL when its range is unbounded.
   */
  protected ?DateBounds $dateBounds = NULL;

  /**
   * How many values a list may hold, or NULL when its count is unbounded.
   */
  protected ?SelectionBounds $selectionBounds = NULL;

  /**
   * The type, extension and size limits on a valid pick.
   */
  protected FilePickerConstraints $pickerConstraints;

  /**
   * The directory a picker opens at and cannot ascend above.
   */
  protected string $pickerStart = '';

  /**
   * Whether a picker shows dot-entries when it opens.
   */
  protected bool $pickerShowHidden = FALSE;

  /**
   * The fixed shape whose slots are filled in, or NULL when there is none.
   */
  protected ?Template $template = NULL;

  /**
   * The caption of a point on a scale, keyed by the point.
   *
   * @var array<int,string>
   */
  protected array $captions = [];

  /**
   * How many rows show at once before the list pages, or NULL for the default.
   */
  protected ?int $pageSize = NULL;

  /**
   * The inline completion candidates, or what computes them.
   *
   * @var list<string>|\Closure
   */
  protected array|\Closure $completion = [];

  /**
   * Whether the leading match is previewed as ghost text after the caret.
   */
  protected bool $ghost = FALSE;

  /**
   * Whether the answer is several values rather than one.
   */
  protected bool $multiple = FALSE;

  /**
   * Whether the editor offers a reveal/hide toggle over a masked value.
   */
  protected bool $revealable = FALSE;

  /**
   * Whether the editor asks twice and refuses a mismatch.
   */
  protected bool $confirm = FALSE;

  /**
   * Whether the editor may hand off to the user's own editor.
   */
  protected bool $externalEditor = FALSE;

  /**
   * Where the editor is drawn: in place on the panel, or full-screen.
   */
  protected RenderMode $renderMode = RenderMode::Inline;

  /**
   * The environment variable answering this field, replacing the derived one.
   */
  protected string $envName = '';

  /**
   * The further environment variables it answers to, in precedence order.
   *
   * @var list<string>
   */
  protected array $envAliases = [];

  /**
   * The static default standing in for a computed one in machine output.
   */
  protected mixed $schemaDefault = NULL;

  /**
   * Whether a static default was declared, so a declared NULL is one.
   */
  protected bool $hasSchemaDefault = FALSE;

  /**
   * Construct a field.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $label
   *   The name it draws.
   * @param \DrevOps\Tui\Model\FieldType $fieldType
   *   The kind of answer it collects, which is what decides the editor it
   *   opens onto and the keys that editor binds.
   */
  public function __construct(
    protected string $id,
    protected string $label,
    protected FieldType $fieldType = FieldType::Text,
  ) {
    $this->pickerConstraints = new FilePickerConstraints();
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * The name this field draws.
   *
   * @return string
   *   The label.
   */
  public function label(): string {
    return $this->label;
  }

  /**
   * The kind of answer this field collects.
   *
   * @return \DrevOps\Tui\Model\FieldType
   *   The type.
   */
  public function type(): FieldType {
    return $this->fieldType;
  }

  /**
   * {@inheritdoc}
   */
  public function mode(): Mode {
    return $this->mode;
  }

  /**
   * {@inheritdoc}
   *
   * The kind is what decides the editor, so opening is where the declaration
   * becomes something that collects.
   */
  public function open(): static {
    // A kind that only draws has nothing to open onto, so it stays as it is
    // rather than failing at the keystroke that reached it.
    if ($this->fieldType->isDisplayOnly()) {
      return $this;
    }

    $this->mode = Mode::Edit;
    $this->draft = $this->value;
    $this->editor = $this->editorFor($this->value);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function close(): static {
    $this->mode = Mode::View;
    $this->draft = NULL;
    $this->editor = NULL;

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Accepting is the editor's to offer and the field's to refuse: a refused
   * value leaves the field open on what was offered, so the reason on its error
   * line is about something still in front of you.
   */
  public function capture(Key $key): bool {
    if (!$this->editor instanceof FieldInterface) {
      return FALSE;
    }

    $this->editor->handle($key);

    if ($this->editor->isCancelled()) {
      $this->close();

      return TRUE;
    }

    if (!$this->editor->isComplete()) {
      $this->draft = $this->editor->value();

      return TRUE;
    }

    $offered = $this->editor->value();

    if (!$this->accept($offered)) {
      $this->draft = $offered;
      $this->editor = $this->editorFor($offered);
    }

    return TRUE;
  }

  /**
   * What this field opened onto.
   *
   * @return \DrevOps\Tui\Field\FieldInterface|null
   *   The editor, or NULL while the field is settled.
   */
  public function editor(): ?FieldInterface {
    return $this->editor;
  }

  /**
   * {@inheritdoc}
   */
  public function draft(mixed $draft): static {
    $this->draft = $draft;

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Declaring a starting point is not offering a value, so nothing is refused
   * here: a default the form author wrote is not a value a person typed.
   */
  public function default(mixed $value): static {
    $this->value = $value;

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * A refused value leaves the answer where it was and the field open, with the
   * reason on its error line.
   */
  public function accept(mixed $value = NULL): bool {
    $offered = func_num_args() === 0 ? $this->draft : $value;

    $refusal = $this->refuses($offered);

    if ($refusal !== NULL) {
      $this->refusal = $refusal;

      return FALSE;
    }

    $this->refusal = NULL;
    $this->value = $this->transform instanceof \Closure ? ($this->transform)($offered) : $offered;
    $this->draft = NULL;
    $this->mode = Mode::View;
    $this->editor = NULL;

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function value(): mixed {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function required(bool $required = TRUE, string $message = ''): static {
    $this->required = $required;
    $this->requiredMessage = $message;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->required;
  }

  /**
   * What is said when a required answer is missing.
   *
   * @return string
   *   The message, empty when one is derived from the label instead.
   */
  public function requiredMessage(): string {
    return $this->requiredMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function requiredViolation(mixed $value): ?string {
    // Strict comparison, so a FALSE confirmation and a zero are answers rather
    // than omissions.
    if (!$this->required || !in_array($value, ['', [], NULL], TRUE)) {
      return NULL;
    }

    if ($this->requiredMessage !== '') {
      return Translator::t($this->requiredMessage);
    }

    return Translator::t('@label is required.', ['@label' => Translator::t($this->label)]);
  }

  /**
   * Refuse values, and say why.
   *
   * @param \Closure $validate
   *   Given the offered value, returns the reason it is refused or NULL.
   *
   * @return static
   *   The field.
   */
  public function validate(\Closure $validate): static {
    $this->validate = $validate;

    return $this;
  }

  /**
   * What refuses a value, and says why.
   *
   * @return \Closure|null
   *   The validator, or NULL when nothing of its own refuses a value.
   */
  public function validator(): ?\Closure {
    return $this->validate;
  }

  /**
   * {@inheritdoc}
   */
  public function transform(\Closure $transform): static {
    $this->transform = $transform;

    return $this;
  }

  /**
   * What normalizes an accepted value before it is held.
   *
   * @return \Closure|null
   *   The transformer, or NULL when a value is held as it was offered.
   */
  public function transformer(): ?\Closure {
    return $this->transform;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple(bool $multiple = TRUE): static {
    $this->multiple = $multiple;

    return $this;
  }

  /**
   * Whether the answer is several values rather than one.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isMultiple(): bool {
    return $this->multiple;
  }

  /**
   * {@inheritdoc}
   */
  public function collectsList(): bool {
    return $this->multiple || $this->fieldType === FieldType::Reorder;
  }

  /**
   * Whether the answer is several picks from the declared rows.
   *
   * Narrower than {@see collectsList()}: a multiple file picker collects a list
   * too, but its items come from the filesystem rather than from the rows.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isMultiChoice(): bool {
    return $this->fieldType === FieldType::Reorder || ($this->multiple && $this->fieldType->constrainsToOptions());
  }

  /**
   * Compute this field's answer from the others.
   *
   * @param \DrevOps\Tui\Derive\Derive $derive
   *   The rule.
   *
   * @return static
   *   The field.
   */
  public function derive(Derive $derive): static {
    $this->derive = $derive;

    return $this;
  }

  /**
   * What computes this field's answer from the others.
   *
   * @return \DrevOps\Tui\Derive\Derive|null
   *   The rule, or NULL when the answer is not computed.
   */
  public function derivation(): ?Derive {
    return $this->derive;
  }

  /**
   * Detect an answer that already exists outside the form.
   *
   * @param \DrevOps\Tui\Discovery\DiscoverInterface|\Closure $discover
   *   The rule, or an `fn (string $directory): mixed` detector of its own.
   *
   * @return static
   *   The field.
   */
  public function discover(DiscoverInterface|\Closure $discover): static {
    $this->discover = $discover;

    return $this;
  }

  /**
   * What detects an answer that already exists outside the form.
   *
   * @return \DrevOps\Tui\Discovery\DiscoverInterface|\Closure|null
   *   The rule, or NULL when nothing is detected.
   */
  public function discovery(): DiscoverInterface|\Closure|null {
    return $this->discover;
  }

  /**
   * Set the environment variable that answers this field.
   *
   * Absolute, so a name published elsewhere is reproduced exactly rather than
   * carrying the form's prefix.
   *
   * @param string $name
   *   The variable name.
   *
   * @return static
   *   The field.
   *
   * @throws \InvalidArgumentException
   *   When the name could not be set portably from a shell.
   */
  public function env(string $name): static {
    $this->assertEnvName($name);
    $this->envName = $name;

    return $this;
  }

  /**
   * The environment variable that answers this field.
   *
   * @return string
   *   The name, empty when the mechanical one stands.
   */
  public function envName(): string {
    return $this->envName;
  }

  /**
   * Set the further environment variables this field also answers to.
   *
   * Consulted in order after the canonical name, so a naming scheme can change
   * without breaking the variables already published.
   *
   * @param array<array-key,string> $names
   *   The alias names, most preferred first.
   *
   * @return static
   *   The field.
   *
   * @throws \InvalidArgumentException
   *   When a name could not be set portably from a shell, or an alias repeats
   *   a name it would never be reached behind.
   */
  public function envAliases(array $names): static {
    $aliases = array_values($names);
    $seen = [];

    foreach ($aliases as $alias) {
      $this->assertEnvName($alias);

      if ($alias === $this->envName || isset($seen[$alias])) {
        throw new \InvalidArgumentException(sprintf('Field "%s" declares the environment variable "%s" twice; only the first would ever be reached, so declare it once.', $this->id, $alias));
      }

      $seen[$alias] = TRUE;
    }

    $this->envAliases = $aliases;

    return $this;
  }

  /**
   * The further environment variables this field also answers to.
   *
   * @return list<string>
   *   The names, in precedence order.
   */
  public function aliases(): array {
    return $this->envAliases;
  }

  /**
   * Advertise a static default where the declared one cannot be resolved.
   *
   * A default computed from the answers has no value until there are some, so
   * machine-readable output stands this in for it rather than resolving it.
   *
   * @param mixed $value
   *   The static value.
   *
   * @return static
   *   The field.
   */
  public function schemaDefault(mixed $value): static {
    $this->schemaDefault = $value;
    $this->hasSchemaDefault = TRUE;

    return $this;
  }

  /**
   * The static default advertised in machine-readable output.
   *
   * @return mixed
   *   The value; meaningful only when one was declared.
   */
  public function schemaDefaultValue(): mixed {
    return $this->schemaDefault;
  }

  /**
   * Whether a static default was declared, so a declared NULL reads as one.
   *
   * @return bool
   *   TRUE when it was.
   */
  public function hasSchemaDefault(): bool {
    return $this->hasSchemaDefault;
  }

  /**
   * Offer an entry for edit mode to open onto.
   *
   * Declaring a value twice replaces the row in place, so the set stays unique
   * and stays in the order it was first declared in.
   *
   * @param string $value
   *   The value it stands for.
   * @param string $label
   *   The label it draws; empty draws the value.
   * @param string $description
   *   What the entry means, shown beside the list.
   * @param bool $disabled
   *   Whether the entry is drawn but cannot be picked.
   * @param string $disabled_reason
   *   Why it cannot be picked.
   *
   * @return static
   *   The field.
   */
  public function entry(string $value, string $label = '', string $description = '', bool $disabled = FALSE, string $disabled_reason = ''): static {
    $entry = new Option($value, $label === '' ? $value : $label, $description, OptionKind::Option, $disabled, $disabled_reason);

    foreach ($this->entries as $index => $existing) {
      if ($existing->kind === OptionKind::Option && $existing->value === $value) {
        $this->entries[$index] = $entry;

        return $this;
      }
    }

    $this->entries[] = $entry;

    return $this;
  }

  /**
   * Head the entries that follow with a group label.
   *
   * @param string $label
   *   The heading.
   *
   * @return static
   *   The field.
   */
  public function heading(string $label): static {
    $this->entries[] = new Option('', $label, '', OptionKind::Heading);

    return $this;
  }

  /**
   * Divide the entries either side of it.
   *
   * @return static
   *   The field.
   */
  public function separator(): static {
    $this->entries[] = new Option('', '', '', OptionKind::Separator);

    return $this;
  }

  /**
   * The rows edit mode opens onto.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The rows, in the order they were declared.
   */
  public function entries(): array {
    return $this->entries;
  }

  /**
   * The entry standing for a value.
   *
   * A row is found by the value it carries rather than by where it sits, so a
   * numeric-looking value stays the string it was declared as.
   *
   * @param string $value
   *   The value.
   *
   * @return \DrevOps\Tui\Model\Option|null
   *   The entry, or NULL when nothing carries that value. Headings and
   *   separators carry none and are never returned.
   */
  public function entryOf(string $value): ?Option {
    foreach ($this->entries as $entry) {
      if ($entry->kind === OptionKind::Option && $entry->value === $value) {
        return $entry;
      }
    }

    return NULL;
  }

  /**
   * The values that can be picked, in the order they are drawn.
   *
   * @return list<string>
   *   The values, excluding headings, separators and disabled entries.
   */
  public function selectableValues(): array {
    return Option::selectableValues($this->entries);
  }

  /**
   * Load the entries on demand, once.
   *
   * @param \Closure $loader
   *   An `fn (): array<string,string>` returning the value => label map.
   *
   * @return static
   *   The field.
   */
  public function load(\Closure $loader): static {
    $this->loader = $loader;

    return $this;
  }

  /**
   * What owes the field one set of entries.
   *
   * @return \Closure|null
   *   The loader, or NULL when the entries stand as declared.
   */
  public function loader(): ?\Closure {
    return $this->loader;
  }

  /**
   * Resolve the entries from the answers, again whenever they change.
   *
   * @param \Closure $resolver
   *   An `fn (array<string,mixed> $answers): array<string,string>` returning
   *   the value => label map.
   *
   * @return static
   *   The field.
   */
  public function resolve(\Closure $resolver): static {
    $this->resolver = $resolver;

    return $this;
  }

  /**
   * What resolves the entries from the answers.
   *
   * @return \Closure|null
   *   The resolver, or NULL when the entries do not follow the answers.
   */
  public function resolver(): ?\Closure {
    return $this->resolver;
  }

  /**
   * Resolve the entries from a live query, again whenever it changes.
   *
   * Unlike a loader it is asked again as the query changes, so the candidates
   * can come from a backend that filters for itself.
   *
   * @param \Closure $source
   *   An
   *   `fn (string $query, array<string,mixed> $answers): array<string,string>`
   *   returning the value => label map.
   *
   * @return static
   *   The field.
   */
  public function query(\Closure $source): static {
    $this->source = $source;

    return $this;
  }

  /**
   * What resolves the entries from a live query.
   *
   * @return \Closure|null
   *   The source, or NULL when the entries do not follow a query.
   */
  public function source(): ?\Closure {
    return $this->source;
  }

  /**
   * Keep a query source quiet until the query is worth sending.
   *
   * @param int $length
   *   The number of characters below which it is not called, at least one.
   *
   * @return static
   *   The field.
   *
   * @throws \InvalidArgumentException
   *   When the length is below one character.
   */
  public function minQuery(int $length): static {
    if ($length < 1) {
      throw new \InvalidArgumentException(sprintf('Field "%s" declares a minimum query length of %d; it must be at least one character.', $this->id, $length));
    }

    $this->queryMinLength = $length;

    return $this;
  }

  /**
   * The query length below which a query source is not called at all.
   *
   * @return int
   *   The length; zero calls it for the empty query too.
   */
  public function queryMinLength(): int {
    return $this->queryMinLength;
  }

  /**
   * Replace the entries with a set a loader, resolver or query resolved to.
   *
   * @param mixed $entries
   *   What the callable returned; anything but a map of strings settles to no
   *   entries, because consumer code running mid-session has no good way to
   *   report a mistake.
   *
   * @return static
   *   The field.
   */
  public function settle(mixed $entries): static {
    $this->entries = Option::resolved($entries);
    // A loader owes the field one list and has now given it; a resolver and a
    // query source answer again as their input changes, so they stand.
    $this->loader = NULL;

    return $this;
  }

  /**
   * Whether the entries stand as declared.
   *
   * @return bool
   *   FALSE while a loader, a resolver or a query source still owes them, so
   *   there is nothing yet to count or to check a value against.
   */
  public function hasSettledEntries(): bool {
    return !$this->loader instanceof \Closure && !$this->resolver instanceof \Closure && !$this->source instanceof \Closure;
  }

  /**
   * Whether the entries follow the answers rather than standing.
   *
   * @return bool
   *   TRUE when they are resolved from the answers or from a live query, so no
   *   one list describes the field.
   */
  public function hasDynamicEntries(): bool {
    return $this->resolver instanceof \Closure || $this->source instanceof \Closure;
  }

  /**
   * {@inheritdoc}
   */
  public function constrain(string $constraint): static {
    $this->constraint = $constraint;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function constraint(): ?string {
    return $this->constraint;
  }

  /**
   * {@inheritdoc}
   */
  public function bounds(NumberBounds $bounds): static {
    $this->bounds = $bounds;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function numberBounds(): ?NumberBounds {
    return $this->bounds;
  }

  /**
   * {@inheritdoc}
   */
  public function dates(DateBounds $bounds): static {
    $this->dateBounds = $bounds;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function dateBounds(): ?DateBounds {
    return $this->dateBounds;
  }

  /**
   * {@inheritdoc}
   */
  public function selections(SelectionBounds $bounds): static {
    $this->selectionBounds = $bounds;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function selectionBounds(): ?SelectionBounds {
    return $this->selectionBounds;
  }

  /**
   * {@inheritdoc}
   */
  public function boundsViolation(mixed $value): ?string {
    return $this->bounds?->violation($value) ?? $this->dateBounds?->violation($value) ?? $this->selectionBounds?->violation($value);
  }

  /**
   * Limit what may be picked from the filesystem.
   *
   * @param \DrevOps\Tui\Model\FilePickerConstraints $constraints
   *   The type, extension and size limits.
   *
   * @return static
   *   The field.
   */
  public function picker(FilePickerConstraints $constraints): static {
    $this->pickerConstraints = $constraints;

    return $this;
  }

  /**
   * What may be picked from the filesystem.
   *
   * @return \DrevOps\Tui\Model\FilePickerConstraints
   *   The limits; unconstrained when none were declared.
   */
  public function pickerConstraints(): FilePickerConstraints {
    return $this->pickerConstraints;
  }

  /**
   * The limit a supplied path falls outside, as a fragment.
   *
   * @param mixed $value
   *   The candidate path, or the list of them.
   *
   * @return string|null
   *   The fragment (e.g. "an existing file"), or NULL when every path meets
   *   every limit.
   */
  public function pickerViolation(mixed $value): ?string {
    // Only a field that browses the filesystem measures a path against it, so
    // limits left on any other kind govern nothing.
    if ($this->fieldType !== FieldType::FilePicker) {
      return NULL;
    }

    return $this->pickerConstraints->violation($value);
  }

  /**
   * Open the browser at a directory it cannot ascend above.
   *
   * @param string $directory
   *   The directory; empty falls back to the working directory.
   *
   * @return static
   *   The field.
   */
  public function startIn(string $directory): static {
    $this->pickerStart = $directory;

    return $this;
  }

  /**
   * The directory the browser opens at.
   *
   * @return string
   *   The directory, empty when it opens where the form runs.
   */
  public function pickerStart(): string {
    return $this->pickerStart;
  }

  /**
   * Show dot-entries when the browser opens.
   *
   * @param bool $show
   *   Whether they are shown.
   *
   * @return static
   *   The field.
   */
  public function showHidden(bool $show = TRUE): static {
    $this->pickerShowHidden = $show;

    return $this;
  }

  /**
   * Whether dot-entries are shown when the browser opens.
   *
   * @return bool
   *   TRUE when they are.
   */
  public function showsHidden(): bool {
    return $this->pickerShowHidden;
  }

  /**
   * Fix the shape of the answer, leaving named slots to fill in.
   *
   * @param \DrevOps\Tui\Model\Template $template
   *   The shape.
   *
   * @return static
   *   The field.
   */
  public function pattern(Template $template): static {
    $this->template = $template;

    return $this;
  }

  /**
   * The shape the answer is filled into.
   *
   * @return \DrevOps\Tui\Model\Template|null
   *   The template, or NULL when the answer has no fixed shape.
   */
  public function template(): ?Template {
    return $this->template;
  }

  /**
   * Why a value does not fit the declared shape, else NULL.
   *
   * An empty string is an unfilled shape, left to the required check; any other
   * value must have the shape and pass every slot's own validator.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The message, or NULL when the value fits or no shape is declared.
   */
  public function templateError(mixed $value): ?string {
    if (!$this->template instanceof Template || !is_string($value) || $value === '') {
      return NULL;
    }

    return $this->template->error($value);
  }

  /**
   * Name what the points of a scale mean.
   *
   * @param array<int,string> $captions
   *   The caption of each point, keyed by the point; points may be captioned
   *   sparsely, and an uncaptioned point still answers with its number.
   *
   * @return static
   *   The field.
   *
   * @throws \InvalidArgumentException
   *   When a caption names a point outside the scale.
   */
  public function captions(array $captions): static {
    foreach (array_keys($captions) as $point) {
      if ($this->bounds instanceof NumberBounds && !$this->bounds->contains($point)) {
        throw new \InvalidArgumentException(sprintf('Field "%s" captions the point %d, which is outside its scale of %s.', $this->id, $point, $this->bounds->describe()));
      }
    }

    $this->captions = $captions;

    return $this;
  }

  /**
   * What the points of the scale mean.
   *
   * @return array<int,string>
   *   The caption of each captioned point, keyed by the point.
   */
  public function ratingCaptions(): array {
    return $this->captions;
  }

  /**
   * The reason a value is not among the entries, as a fragment, else NULL.
   *
   * A fragment rather than a sentence, so each caller frames it its own way.
   *
   * @param mixed $value
   *   The candidate value - one value, or the list of them.
   *
   * @return string|null
   *   The fragment, or NULL when nothing constrains the value or every item is
   *   among the entries.
   */
  public function entryError(mixed $value): ?string {
    // A field that declares no entries constrains nothing - but one whose
    // entries follow a query or the answers is constrained by whatever they
    // resolved to, and resolving to nothing means the value does not exist.
    if (!$this->fieldType->constrainsToOptions() || ($this->entries === [] && !$this->hasDynamicEntries())) {
      return NULL;
    }

    if (!$this->isMultiChoice()) {
      return $this->scalarEntryError(is_scalar($value) ? (string) $value : '');
    }

    if (!is_array($value)) {
      return Translator::t('value must be a list');
    }

    foreach ($value as $item) {
      $error = $this->scalarEntryError(is_scalar($item) ? (string) $item : '');

      if ($error !== NULL) {
        return $error;
      }
    }

    return $this->fieldType === FieldType::Reorder ? $this->rankingError($value) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function refusal(): ?string {
    return $this->refusal;
  }

  /**
   * Set the explanatory text drawn under this field while it is open.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The field.
   */
  public function description(string $description): static {
    $this->description = $description;

    return $this;
  }

  /**
   * The explanatory text drawn under this field while it is open.
   *
   * @return string
   *   The description, empty when it offers none.
   */
  public function descriptionText(): string {
    return $this->description;
  }

  /**
   * Set the long-form text behind this field's help key.
   *
   * @param string $help
   *   The help.
   *
   * @return static
   *   The field.
   */
  public function help(string $help): static {
    $this->help = $help;

    return $this;
  }

  /**
   * The long-form text behind this field's help key.
   *
   * @return string
   *   The help, empty when it offers none.
   */
  public function helpText(): string {
    return $this->help;
  }

  /**
   * Set the ghost text shown while the editor's buffer is empty.
   *
   * @param string $placeholder
   *   The ghost text; it never becomes a value.
   *
   * @return static
   *   The field.
   */
  public function placeholder(string $placeholder): static {
    $this->placeholder = $placeholder;

    return $this;
  }

  /**
   * The ghost text shown while the editor's buffer is empty.
   *
   * @return string
   *   The ghost text, empty when the buffer stays bare.
   */
  public function placeholderText(): string {
    return $this->placeholder;
  }

  /**
   * Complete what is being typed from a set of candidates.
   *
   * @param list<string>|\Closure $source
   *   The candidates, or an
   *   `fn (array<string,mixed> $answers): list<string>` computing them from the
   *   answers collected so far.
   *
   * @return static
   *   The field.
   */
  public function complete(array|\Closure $source): static {
    $this->completion = $source;

    return $this;
  }

  /**
   * What completes what is being typed.
   *
   * @return list<string>|\Closure
   *   The candidates, or what computes them; empty offers no completion.
   */
  public function completion(): array|\Closure {
    return $this->completion;
  }

  /**
   * Preview the leading match as ghost text after the caret.
   *
   * @param bool $ghost
   *   Whether it is previewed.
   *
   * @return static
   *   The field.
   */
  public function ghost(bool $ghost = TRUE): static {
    $this->ghost = $ghost;

    return $this;
  }

  /**
   * Whether the leading match is previewed as ghost text.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function hasGhost(): bool {
    return $this->ghost;
  }

  /**
   * Bound how many rows show at once before the list pages.
   *
   * @param int $size
   *   The rows shown at once, at least one.
   *
   * @return static
   *   The field.
   *
   * @throws \InvalidArgumentException
   *   When the size is below one row.
   */
  public function paginate(int $size): static {
    if ($size < 1) {
      throw new \InvalidArgumentException(sprintf('Field "%s" declares a page of %d rows; a page shows at least one.', $this->id, $size));
    }

    $this->pageSize = $size;

    return $this;
  }

  /**
   * How many rows show at once before the list pages.
   *
   * @return int|null
   *   The rows, or NULL for as many as the editor shows by default.
   */
  public function pageSize(): ?int {
    return $this->pageSize;
  }

  /**
   * Offer a reveal/hide toggle over a masked value.
   *
   * @param bool $revealable
   *   Whether the toggle is offered.
   *
   * @return static
   *   The field.
   */
  public function revealable(bool $revealable = TRUE): static {
    $this->revealable = $revealable;

    return $this;
  }

  /**
   * Whether a reveal/hide toggle is offered over a masked value.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isRevealable(): bool {
    return $this->revealable;
  }

  /**
   * Ask twice, and refuse a mismatch before accepting.
   *
   * @param bool $confirm
   *   Whether it asks twice.
   *
   * @return static
   *   The field.
   */
  public function confirmation(bool $confirm = TRUE): static {
    $this->confirm = $confirm;

    return $this;
  }

  /**
   * Whether the editor asks twice and refuses a mismatch.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function hasConfirmation(): bool {
    return $this->confirm;
  }

  /**
   * Allow the editor to hand off to the user's own editor.
   *
   * @param bool $enabled
   *   Whether the handoff is offered.
   *
   * @return static
   *   The field.
   */
  public function externalEditor(bool $enabled = TRUE): static {
    $this->externalEditor = $enabled;

    return $this;
  }

  /**
   * Whether the editor may hand off to the user's own editor.
   *
   * @return bool
   *   TRUE when it may.
   */
  public function hasExternalEditor(): bool {
    return $this->externalEditor;
  }

  /**
   * Open the editor full-screen rather than in place on the panel.
   *
   * @param bool $standalone
   *   TRUE for the full-screen editor; FALSE to edit in place.
   *
   * @return static
   *   The field.
   */
  public function standalone(bool $standalone = TRUE): static {
    $this->renderMode = $standalone ? RenderMode::Standalone : RenderMode::Inline;

    return $this;
  }

  /**
   * Where the editor is drawn.
   *
   * @return \DrevOps\Tui\Model\RenderMode
   *   In place on the panel, or full-screen on its own.
   */
  public function renderMode(): RenderMode {
    return $this->renderMode;
  }

  /**
   * {@inheritdoc}
   *
   * A settled field takes no key at all: the cursor and what it opens belong to
   * the panel, so every key travels outward until something is open.
   */
  public function binds(Key $key): bool {
    if ($this->mode !== Mode::Edit) {
      return FALSE;
    }

    // Every printable key is something being typed where the kind takes typed
    // input, which is why the help key reaches an open text field and travels
    // outward from a closed one without either being written as an exception.
    if ($key->isChar() && $this->keyScope()->consumesText()) {
      return TRUE;
    }

    return $this->boundAction($key) instanceof Action;
  }

  /**
   * {@inheritdoc}
   *
   * While it is open the field is its editor, so the keys it answers to are the
   * ones the editor was wired with rather than a second copy of them.
   */
  public function bindings(): ScopedKeyMap {
    return $this->editor instanceof FieldInterface ? $this->editor->keys() : $this->keyMap()->scope($this->keyScope());
  }

  /**
   * {@inheritdoc}
   *
   * A settled field advertises nothing, because nothing reaches it.
   */
  public function hints(): array {
    return $this->editor instanceof FieldInterface ? $this->editor->hints() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, FieldElementsInterface::class, 'a field');

    // Help is never drawn in the row that offers it: it can run to paragraphs,
    // so the row marks that there is something to ask for and nothing more.
    $marker = $this->help === '' ? '' : ' ' . $elements->fieldHelpMarker();
    $label = $elements->fieldSelector($this->isFocused()) . ' ' . $elements->fieldLabel($this->label) . $marker;

    return $this->mode === Mode::View
      ? rtrim($label . '  ' . $elements->fieldValue($this->readable($elements)))
      : $this->openLines($theme, $elements, $label);
  }

  /**
   * {@inheritdoc}
   */
  protected function keyScope(): Scope {
    return Scope::field($this->fieldType, $this->multiple);
  }

  /**
   * The editor this field's kind opens onto, seeded with a value.
   *
   * @param mixed $current
   *   The value it starts from.
   *
   * @return \DrevOps\Tui\Field\FieldInterface
   *   The editor.
   */
  protected function editorFor(mixed $current): FieldInterface {
    return (new FieldFactory($this->keyMap()))->open($this, $current);
  }

  /**
   * The reason an offered value is refused, or NULL when it is acceptable.
   *
   * Emptiness is answered first, so a required field says so rather than
   * letting an empty list read as a count violation; the field's own validator
   * runs after the declared limits, so it sees a value that already fits them.
   *
   * @param mixed $value
   *   The offered value.
   *
   * @return string|null
   *   The reason, or NULL when nothing refuses it.
   */
  protected function refuses(mixed $value): ?string {
    $missing = $this->requiredViolation($value);

    if ($missing !== NULL) {
      return $missing;
    }

    $outside = $this->boundsViolation($value) ?? $this->pickerViolation($value);

    if ($outside !== NULL) {
      return Translator::t('must be @constraint.', ['@constraint' => $outside]);
    }

    // The shape is checked before the field's own validator, which reads the
    // value as an assembled template and would otherwise see a foreign string.
    $misshapen = $this->templateError($value);

    if ($misshapen !== NULL) {
      return $misshapen;
    }

    $refusal = $this->validate instanceof \Closure ? ($this->validate)($value) : NULL;

    return $refusal ?? $this->entryError($value);
  }

  /**
   * The reason one value is not among the entries, as a fragment, else NULL.
   *
   * @param string $value
   *   The candidate value.
   *
   * @return string|null
   *   The fragment naming the value when it is disabled or unknown, or NULL
   *   when it can be picked.
   */
  protected function scalarEntryError(string $value): ?string {
    if (in_array($value, $this->selectableValues(), TRUE)) {
      return NULL;
    }

    // Listing what is allowed is what makes the message useful, so when a query
    // found nothing there is no list to offer and naming the value is all that
    // can honestly be said.
    if ($this->entries === []) {
      return Translator::t('value "@value" was not found', ['@value' => $value]);
    }

    $entry = $this->entryOf($value);

    if ($entry instanceof Option && $entry->disabled) {
      if ($entry->disabledReason === '') {
        return Translator::t('option "@value" is disabled', ['@value' => $value]);
      }

      return Translator::t('option "@value" is disabled: @reason', [
        '@value' => $value,
        '@reason' => $entry->disabledReason,
      ]);
    }

    return Translator::t('value "@value" is not one of: @options', [
      '@value' => $value,
      '@options' => implode(', ', $this->selectableValues()),
    ]);
  }

  /**
   * The reason a ranking is not a full ordering of the entries, else NULL.
   *
   * Membership is checked by the caller, so a ranking that is a full ordering
   * has as many items as there are entries, with no repeats.
   *
   * @param array<array-key,mixed> $items
   *   The ranking, already known to hold only values that can be picked.
   *
   * @return string|null
   *   The fragment, or NULL when the ranking covers every entry once.
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
   * Reject an environment variable name that could not be honoured.
   *
   * @param string $name
   *   The declared name.
   *
   * @throws \InvalidArgumentException
   *   When the name could not be set portably from a shell.
   */
  protected function assertEnvName(string $name): void {
    if (preg_match(self::ENV_NAME_PATTERN, $name) !== 1) {
      throw new \InvalidArgumentException(sprintf('Field "%s" declares the environment variable name "%s", which is not portable; use letters, digits and underscores, starting with a letter or underscore.', $this->id, $name));
    }
  }

  /**
   * The rows this field draws while it is open.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\FieldElementsInterface $elements
   *   The same theme, narrowed to the elements a field draws with.
   * @param string $label
   *   The already-styled label.
   *
   * @return string
   *   The rows.
   */
  protected function openLines(ThemeInterface $theme, FieldElementsInterface $elements, string $label): string {
    $lines = [];
    // Measured from the label as it was drawn, not from its source text: it may
    // carry a help marker and styling, and only its visible width lines the
    // rows up under it.
    $indent = str_repeat(' ', Ansi::width($label) + 2);

    foreach ($this->valueRegion($theme, $elements) as $row) {
      $lines[] = rtrim(($lines === [] ? $label . '  ' : $indent) . $row);
    }

    if ($this->description !== '') {
      $lines[] = $indent . $elements->fieldDescription($this->description);
    }

    // The two share one line and never appear together: a constraint says what
    // is acceptable, and an error replaces it the instant something is not.
    if ($this->refusal !== NULL) {
      $lines[] = $indent . $elements->fieldError($this->refusal);
    }
    elseif ($this->constraint !== NULL) {
      $lines[] = $indent . $elements->fieldConstraint($this->constraint);
    }

    return implode("\n", $lines);
  }

  /**
   * What fills the region right of the label while the field is open.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Element\FieldElementsInterface $elements
   *   The same theme, narrowed to the elements a field draws with.
   *
   * @return list<string>
   *   The rows, at least one.
   */
  protected function valueRegion(ThemeInterface $theme, FieldElementsInterface $elements): array {
    // The label and the selector stay put; only this region changes shape - and
    // once something is open, its shape is whatever that thing draws.
    if ($this->editor instanceof FieldInterface) {
      return explode("\n", $this->editor->view($theme));
    }

    if ($this->entries === []) {
      return [$elements->fieldValue($this->readable($elements))];
    }

    return array_map(fn(Option $entry): string => $this->entryLine($elements, $entry), $this->entries);
  }

  /**
   * One entry as it is drawn.
   *
   * @param \DrevOps\Tui\Block\Element\FieldElementsInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $entry
   *   The entry.
   *
   * @return string
   *   The drawn entry; empty for a divider, which is a gap and nothing else.
   */
  protected function entryLine(FieldElementsInterface $theme, Option $entry): string {
    if ($entry->kind === OptionKind::Heading) {
      return $theme->fieldCaption($entry->label);
    }

    if ($entry->kind === OptionKind::Separator) {
      return '';
    }

    // Marking and naming are two elements: the mark records what was picked
    // and the text says what it was, so a theme can restyle either alone.
    $chosen = $this->isChosen($entry);
    $line = $theme->fieldEntryMarker($chosen) . ' ' . $theme->fieldEntry($entry->label, $chosen);

    // Why an entry cannot be picked belongs beside it, or a row that is drawn
    // and refuses the cursor reads as a fault rather than a decision.
    return $entry->disabled && $entry->disabledReason !== '' ? $line . ' ' . $theme->fieldEntryNote($entry->disabledReason) : $line;
  }

  /**
   * Whether an entry is the one picked.
   *
   * @param \DrevOps\Tui\Model\Option $entry
   *   The entry.
   *
   * @return bool
   *   TRUE when the answer being drawn holds its value.
   */
  protected function isChosen(Option $entry): bool {
    $shown = $this->mode === Mode::Edit ? $this->draft : $this->value;

    if (!is_array($shown)) {
      return is_scalar($shown) && (string) $shown === $entry->value;
    }

    foreach ($shown as $item) {
      if (is_scalar($item) && (string) $item === $entry->value) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * The answer as it reads on one line.
   *
   * @param \DrevOps\Tui\Block\Element\FieldElementsInterface $theme
   *   The theme.
   *
   * @return string
   *   The value, or the draft while one is being typed.
   */
  protected function readable(FieldElementsInterface $theme): string {
    $shown = $this->mode === Mode::Edit ? $this->draft : $this->value;

    if (is_array($shown)) {
      return implode($theme->fieldValueSeparator(), array_map(static fn(mixed $part): string => is_scalar($part) ? (string) $part : '', $shown));
    }

    return $shown === NULL ? '' : (is_scalar($shown) ? (string) $shown : '');
  }

}
