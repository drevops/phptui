<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\FieldElementsInterface;
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
final class Field implements BlockInterface {

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
  protected ?string $error = NULL;

  /**
   * The long-form text behind its help key.
   */
  protected string $help = '';

  /**
   * What decides whether it is asked for at all.
   *
   * @var \Closure(): bool|null
   */
  protected ?\Closure $when = NULL;

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
   * The id this field is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Which of its two shapes this field is drawing.
   *
   * @return \DrevOps\Tui\Block\Mode
   *   The mode.
   */
  public function mode(): Mode {
    return $this->mode;
  }

  /**
   * Open this field to collect an answer.
   *
   * @return $this
   *   The field.
   */
  public function open(): self {
    $this->mode = Mode::Edit;
    $this->draft = $this->value;

    return $this;
  }

  /**
   * Close this field, discarding whatever was being typed.
   *
   * @return $this
   *   The field.
   */
  public function close(): self {
    $this->mode = Mode::View;
    $this->draft = NULL;

    return $this;
  }

  /**
   * Set what is being typed, before it is accepted.
   *
   * @param mixed $draft
   *   The draft.
   *
   * @return $this
   *   The field.
   */
  public function draft(mixed $draft): self {
    $this->draft = $draft;

    return $this;
  }

  /**
   * Seed the answer this field starts with.
   *
   * Declaring a starting point is not offering a value, so nothing is refused
   * here: a default the form author wrote is not a value a person typed.
   *
   * @param mixed $value
   *   The value.
   *
   * @return $this
   *   The field.
   */
  public function default(mixed $value): self {
    $this->value = $value;

    return $this;
  }

  /**
   * Offer a value, or the draft when none is given.
   *
   * @param mixed $value
   *   The value, or omitted to offer whatever is being typed.
   *
   * @return bool
   *   Whether it was accepted. A refused value leaves the answer where it was
   *   and the field open, with the reason on its error line.
   */
  public function accept(mixed $value = NULL): bool {
    $offered = func_num_args() === 0 ? $this->draft : $value;

    if ($this->validate instanceof \Closure) {
      $refusal = ($this->validate)($offered);

      if ($refusal !== NULL) {
        $this->error = $refusal;

        return FALSE;
      }
    }

    $this->error = NULL;
    $this->value = $offered;
    $this->draft = NULL;
    $this->mode = Mode::View;

    return TRUE;
  }

  /**
   * The answer this field contributes to the result.
   *
   * @return mixed
   *   The value, or NULL until one is accepted.
   */
  public function value(): mixed {
    return $this->value;
  }

  /**
   * Offer an entry for edit mode to open onto.
   *
   * @param string $value
   *   The value it stands for.
   * @param string $label
   *   The label it draws.
   *
   * @return $this
   *   The field.
   */
  public function entry(string $value, string $label): self {
    $this->entries[$value] = $label;

    return $this;
  }

  /**
   * State what this field will accept, before anything is refused.
   *
   * @param string $constraint
   *   The constraint, as an unframed phrase its caller wraps.
   *
   * @return $this
   *   The field.
   */
  public function constrain(string $constraint): self {
    $this->constraint = $constraint;

    return $this;
  }

  /**
   * What this field will accept.
   *
   * @return string|null
   *   The constraint, or NULL when it states none.
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
   * @return $this
   *   The field.
   */
  public function validate(\Closure $validate): self {
    $this->validate = $validate;

    return $this;
  }

  /**
   * Why the last value was refused.
   *
   * @return string|null
   *   The message, or NULL when nothing is refused.
   */
  public function error(): ?string {
    return $this->error;
  }

  /**
   * Decide whether this field is asked for at all.
   *
   * @param \Closure $when
   *   Returns whether it is.
   *
   * @return $this
   *   The field.
   */
  public function when(\Closure $when): self {
    $this->when = $when;

    return $this;
  }

  /**
   * Whether this field is asked for.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isActive(): bool {
    return !$this->when instanceof \Closure || ($this->when)();
  }

  /**
   * Set the long-form text behind this field's help key.
   *
   * @param string $help
   *   The help.
   *
   * @return $this
   *   The field.
   */
  public function help(string $help): self {
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
    if (!$theme instanceof FieldElementsInterface) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw a field: it does not implement %s.', $theme::class, FieldElementsInterface::class));
    }

    // Help is never drawn in the row that offers it: it can run to paragraphs,
    // so the row marks that there is something to ask for and nothing more.
    $label = $theme->fieldLabel($this->label) . ($this->help === '' ? '' : ' ' . $theme->fieldHelpMarker());

    return $this->mode === Mode::View
      ? rtrim($label . '  ' . $theme->fieldValue($this->readable()))
      : $this->openLines($theme, $label);
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
      $lines[] = ($lines === [] ? $label . '  ' : $indent) . $theme->fieldEntry($entry, $value === $this->draft);
    }

    if ($lines === []) {
      $lines[] = rtrim($label . '  ' . $theme->fieldValue($this->readable()));
    }

    // The two share one line and never appear together: a constraint says what
    // is acceptable, and an error replaces it the instant something is not.
    if ($this->error !== NULL) {
      $lines[] = $indent . $theme->fieldError($this->error);
    }
    elseif ($this->constraint !== NULL) {
      $lines[] = $indent . $theme->fieldConstraint($this->constraint);
    }

    return implode("\n", $lines);
  }

  /**
   * The answer as it reads on one line.
   *
   * @return string
   *   The value, or the draft while one is being typed.
   */
  protected function readable(): string {
    $shown = $this->mode === Mode::Edit ? $this->draft : $this->value;

    if (is_array($shown)) {
      return implode(', ', array_map(static fn(mixed $part): string => is_scalar($part) ? (string) $part : '', $shown));
    }

    return $shown === NULL ? '' : (is_scalar($shown) ? (string) $shown : '');
  }

}
