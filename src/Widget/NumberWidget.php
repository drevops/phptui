<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;

/**
 * Integer input: digits with an optional leading minus, accepted as an int.
 *
 * With bounds supplied, Up/Down adjust the value by the step clamped to the
 * range and an out-of-range entry is rejected inline; without bounds the widget
 * is a plain integer text entry with the arrow keys inert.
 *
 * @package DrevOps\Tui\Widget
 */
class NumberWidget extends AbstractWidget implements TextEditCapableInterface, StepCapableInterface {

  use TextEditCapableTrait {
    insert as protected insertText;
  }

  /**
   * Construct a number widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param \DrevOps\Tui\Model\NumberBounds|null $bounds
   *   Optional bounds and step; NULL for a plain integer entry.
   */
  public function __construct(string $buffer = '', protected ?NumberBounds $bounds = NULL) {
    $this->initTextBuffer($buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Number);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->bounds instanceof NumberBounds) {
      if ($keys->matches($key, Action::Increment)) {
        $this->stepBy(1);

        return;
      }

      if ($keys->matches($key, Action::Decrement)) {
        $this->stepBy(-1);

        return;
      }
    }

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    $this->handleTextEditKey($key);
  }

  /**
   * {@inheritdoc}
   *
   * Only a digit, or a leading minus not yet present, enters the buffer.
   */
  public function insert(string $text): void {
    if ($text === '-') {
      if ($this->cursor !== 0 || str_contains($this->buffer, '-')) {
        return;
      }
    }
    elseif (!ctype_digit($text)) {
      return;
    }

    $this->insertText($text);
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return (int) $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accept(mixed $value): bool {
    $violation = $this->bounds?->violation($value);
    if ($violation !== NULL) {
      $this->error = Translator::t('Enter a number @constraint.', ['@constraint' => $violation]);

      return FALSE;
    }

    return parent::accept($value);
  }

  /**
   * {@inheritdoc}
   *
   * Each position is one bounds step, clamped to the range; without bounds the
   * value has no step to move by, so the call is inert.
   */
  public function stepBy(int $delta): void {
    if (!$this->bounds instanceof NumberBounds || $delta === 0) {
      return;
    }

    $this->buffer = (string) $this->bounds->step((int) $this->buffer, $delta);
    $this->cursor = Strings::length($this->buffer);
    $this->error = NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->renderInputLine($theme);
  }

  /**
   * {@inheritdoc}
   *
   * The step keys are the non-obvious binding here - nothing else signals that
   * they adjust the value - so they lead when bounds are set.
   */
  #[\Override]
  public function hints(): array {
    if (!$this->bounds instanceof NumberBounds) {
      return parent::hints();
    }

    return [new Hint('adjust', Action::Increment, Action::Decrement), ...parent::hints()];
  }

}
