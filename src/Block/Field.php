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
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;

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
   * The entries edit mode opens onto, keyed by value.
   *
   * @var array<string,string>
   */
  protected array $entries = [];

  /**
   * What this field will accept, stated before anything is refused.
   */
  protected ?string $constraint = NULL;

  /**
   * Why the last value was refused, until one is acceptable again.
   */
  protected ?string $refusal = NULL;

  /**
   * The long-form text behind its help key.
   */
  protected string $help = '';

  /**
   * What refuses a value, and says why.
   *
   * @var \Closure(mixed): ?string|null
   */
  protected ?\Closure $validate = NULL;

  /**
   * Construct a field.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $label
   *   The name it draws.
   */
  public function __construct(
    protected string $id,
    protected string $label,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function mode(): Mode {
    return $this->mode;
  }

  /**
   * {@inheritdoc}
   */
  public function open(): static {
    $this->mode = Mode::Edit;
    $this->draft = $this->value;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function close(): static {
    $this->mode = Mode::View;
    $this->draft = NULL;

    return $this;
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

    if ($this->validate instanceof \Closure) {
      $refusal = ($this->validate)($offered);

      if ($refusal !== NULL) {
        $this->refusal = $refusal;

        return FALSE;
      }
    }

    $this->refusal = NULL;
    $this->value = $offered;
    $this->draft = NULL;
    $this->mode = Mode::View;

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
  public function binds(Key $key): bool {
    // Every printable key is something being typed while it is open, which is
    // why the help key reaches an open field and travels outward from a closed
    // one without either being written as an exception.
    if ($this->mode === Mode::Edit && $key->isChar()) {
      return TRUE;
    }

    return in_array($key->name, $this->bindings, TRUE);
  }

  /**
   * Offer an entry for edit mode to open onto.
   *
   * @param string $value
   *   The value it stands for.
   * @param string $label
   *   The label it draws.
   *
   * @return static
   *   The field.
   */
  public function entry(string $value, string $label): static {
    $this->entries[$value] = $label;

    return $this;
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
   * {@inheritdoc}
   */
  public function refusal(): ?string {
    return $this->refusal;
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
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, FieldElementsInterface::class, 'a field');

    // Help is never drawn in the row that offers it: it can run to paragraphs,
    // so the row marks that there is something to ask for and nothing more.
    $label = $elements->fieldLabel($this->label) . ($this->help === '' ? '' : ' ' . $elements->fieldHelpMarker());

    return $this->mode === Mode::View
      ? rtrim($label . '  ' . $elements->fieldValue($this->readable($elements)))
      : $this->openLines($elements, $label);
  }

  /**
   * The rows this field draws while it is open.
   *
   * @param \DrevOps\Tui\Block\Element\FieldElementsInterface $theme
   *   The theme.
   * @param string $label
   *   The already-styled label.
   *
   * @return string
   *   The rows.
   */
  protected function openLines(FieldElementsInterface $theme, string $label): string {
    $lines = [];
    // Measured from the label as it was drawn, not from its source text: it may
    // carry a help marker and styling, and only its visible width lines the
    // entries up under it.
    $indent = str_repeat(' ', Ansi::width($label) + 2);

    foreach ($this->entries as $value => $entry) {
      // Marking and naming are two elements: the mark records what was picked
      // and the text says what it was, so a theme can restyle either alone.
      $chosen = $value === $this->draft;
      $lines[] = ($lines === [] ? $label . '  ' : $indent) . $theme->fieldEntryMarker($chosen) . ' ' . $theme->fieldEntry($entry, $chosen);
    }

    if ($lines === []) {
      $lines[] = rtrim($label . '  ' . $theme->fieldValue($this->readable($theme)));
    }

    // The two share one line and never appear together: a constraint says what
    // is acceptable, and an error replaces it the instant something is not.
    if ($this->refusal !== NULL) {
      $lines[] = $indent . $theme->fieldError($this->refusal);
    }
    elseif ($this->constraint !== NULL) {
      $lines[] = $indent . $theme->fieldConstraint($this->constraint);
    }

    return implode("\n", $lines);
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
