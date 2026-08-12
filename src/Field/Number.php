<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\NumberBounds;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\PhpTui\Field\Capability\StepCapableInterface;
use DrevOps\PhpTui\Field\Capability\TextEditCapableInterface;
use DrevOps\PhpTui\Field\Capability\TextEditCapableTrait;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Utils\Strings;

/**
 * Integer input: digits with an optional leading minus, accepted as an int.
 *
 * With bounds supplied, Up/Down adjust the value by the step clamped to the
 * range; without bounds the field is a plain integer text entry with the arrow
 * keys inert. The bounds move the value here and never refuse one: what the
 * answer must be is measured where the answer is held.
 *
 * @package DrevOps\PhpTui\Field
 */
class Number extends AbstractField implements TextEditCapableInterface, StepCapableInterface, PlaceholderCapableInterface {

  use TextEditCapableTrait {
    insert as protected insertText;
  }
  use PlaceholderCapableTrait;

  /**
   * Construct a number field.
   *
   * @param string $default
   *   The initial value (and live input buffer).
   * @param \DrevOps\PhpTui\Block\NumberBounds|null $bounds
   *   Optional bounds and step; NULL for a plain integer entry.
   */
  public function __construct(string $default = '', protected ?NumberBounds $bounds = NULL) {
    $this->initTextBuffer($default);
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
    return $this->renderInputLine($theme, $this->placeholderText($this->buffer));
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
