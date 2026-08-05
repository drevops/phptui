<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Template as TemplateModel;
use DrevOps\Tui\Field\Capability\TextEditCapableInterface;
use DrevOps\Tui\Field\Capability\TextEditCapableTrait;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Fills the named slots of a fixed shape, one slot at a time.
 *
 * The shape's fixed text is drawn as context and each slot is edited in place;
 * moving between slots swaps the edit buffer, so only the slot holding the
 * caret is live. Each slot carries its own validator, checked as the slot is
 * left and again for every slot on accept, and the accepted value is the
 * assembled string.
 *
 * @package DrevOps\Tui\Field
 */
class Template extends AbstractField implements TextEditCapableInterface {

  use TextEditCapableTrait;

  /**
   * The slot names, in the order they appear in the shape.
   *
   * @var list<string>
   */
  protected array $names;

  /**
   * The value of each slot, keyed by slot name.
   *
   * @var array<string,string>
   */
  protected array $parts = [];

  /**
   * The index of the slot holding the caret.
   */
  protected int $active = 0;

  /**
   * Construct a template field.
   *
   * @param \DrevOps\Tui\Block\Template $template
   *   The shape to fill in.
   * @param string $default
   *   The initial assembled value; a value that does not have the shape leaves
   *   every slot empty.
   */
  public function __construct(protected TemplateModel $template, string $default = '') {
    $this->names = $this->template->placeholders();
    $extracted = $this->template->extract($default);

    foreach ($this->names as $name) {
      $this->parts[$name] = $extracted[$name] ?? '';
    }

    $this->focus(0);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Template);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->move(1);

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->move(-1);

      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      $this->submit();

      return;
    }

    $this->handleTextEditKey($key);
  }

  /**
   * {@inheritdoc}
   *
   * The assembled string, with the live buffer standing in for its slot.
   */
  #[\Override]
  protected function liveValue(): mixed {
    return $this->template->assemble($this->values());
  }

  /**
   * {@inheritdoc}
   *
   * Moving between slots is the action a reader will not guess, so it leads.
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('move between parts', Action::MoveDown, Action::MoveUp), ...parent::hints()];
  }

  /**
   * The value of every slot, with the live buffer standing in for its slot.
   *
   * @return array<string,string>
   *   The slot values keyed by slot name, in shape order.
   */
  protected function values(): array {
    $values = $this->parts;
    $values[$this->activeName()] = $this->buffer;

    return $values;
  }

  /**
   * The name of the slot holding the caret.
   *
   * @return string
   *   The slot name.
   */
  protected function activeName(): string {
    return $this->names[$this->active] ?? '';
  }

  /**
   * Move the caret to another slot, wrapping around the ends.
   *
   * The slot being left is validated on the way out: a rejected value shows its
   * error but does not hold the caret, so a slot filled in the wrong order can
   * still be reached and corrected.
   *
   * @param int $direction
   *   The number of slots to move by: 1 forward, -1 back.
   */
  protected function move(int $direction): void {
    $count = count($this->names);
    $this->parts[$this->activeName()] = $this->buffer;
    $this->error = $this->template->partError($this->activeName(), $this->buffer);

    $this->focus((($this->active + $direction) % $count + $count) % $count);
  }

  /**
   * Put the caret on a slot, loading its value into the edit buffer.
   *
   * @param int $index
   *   The slot index.
   */
  protected function focus(int $index): void {
    $this->active = $index;
    $this->initTextBuffer($this->parts[$this->activeName()] ?? '');
  }

  /**
   * Accept the assembled value once every slot passes its own validator.
   *
   * A rejected slot takes the caret, so the shown error names the slot the user
   * is looking at.
   */
  protected function submit(): void {
    $values = $this->values();
    $this->parts = $values;

    foreach ($this->names as $index => $name) {
      $error = $this->template->partError($name, $values[$name] ?? '');
      if ($error !== NULL) {
        $this->focus($index);
        $this->error = $error;

        return;
      }
    }

    if (!$this->rejectAmbiguous($values)) {
      return;
    }

    $this->accept($this->template->assemble($values));
  }

  /**
   * Refuse a slot whose value would be misread once the shape is assembled.
   *
   * @param array<string,string> $values
   *   The value of each slot, keyed by slot name.
   *
   * @return bool
   *   TRUE when every slot survives assembly; FALSE when one was rejected, the
   *   caret moved to it and the error set.
   */
  protected function rejectAmbiguous(array $values): bool {
    $name = $this->template->ambiguousSlot($values);
    if ($name === NULL) {
      return TRUE;
    }

    $index = (int) array_search($name, $this->names, TRUE);
    $this->focus($index);
    $this->error = Translator::t('@label: must not contain "@text".', [
      '@label' => $this->template->labelOf($name),
      '@text' => $this->template->literalAt($index + 1),
    ]);

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $shape = '';

    foreach ($this->names as $index => $name) {
      $shape .= $this->renderLiteral($theme, $index) . $this->renderSlot($theme, $index, $name);
    }

    $shape .= $this->renderLiteral($theme, count($this->names));

    return $shape . "\n" . $this->elements($theme)->fieldState(Translator::t('filling in @label', ['@label' => $this->template->labelOf($this->activeName())]));
  }

  /**
   * Render one chunk of the shape's fixed text, dimmed to read as context.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the dimmed styling.
   * @param int $index
   *   The chunk position.
   *
   * @return string
   *   The rendered chunk; an absent chunk styles to nothing rather than to a
   *   bare pair of styling codes.
   */
  protected function renderLiteral(ThemeInterface $theme, int $index): string {
    $literal = $this->template->literalAt($index);

    return $literal === '' ? '' : $this->elements($theme)->fieldDescription($literal);
  }

  /**
   * Render one slot: the live caret line, a filled value, or a dimmed hint.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the caret glyph and the dimmed styling.
   * @param int $index
   *   The slot index.
   * @param string $name
   *   The slot name.
   *
   * @return string
   *   The rendered slot.
   */
  protected function renderSlot(ThemeInterface $theme, int $index, string $name): string {
    if ($index === $this->active) {
      return $this->renderCaretLine($theme);
    }

    $value = $this->parts[$name] ?? '';

    // An empty slot would collapse the shape into its fixed text alone, so it
    // shows its label instead - dimmed, to read as a hint and not a value.
    return $value === '' ? $this->elements($theme)->fieldDescription($this->template->labelOf($name)) : $value;
  }

}
