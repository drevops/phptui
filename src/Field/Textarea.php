<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Field\Capability\ExternalEditCapableInterface;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\PhpTui\Field\Capability\TextEditCapableInterface;
use DrevOps\PhpTui\Field\Capability\TextEditCapableTrait;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Utils\Strings;

/**
 * Multi-line text input: Enter inserts a newline, Tab accepts.
 *
 * @package DrevOps\PhpTui\Field
 */
class Textarea extends AbstractField implements TextEditCapableInterface, ExternalEditCapableInterface, PlaceholderCapableInterface {

  use TextEditCapableTrait;
  use PlaceholderCapableTrait;

  /**
   * Whether the external-editor handoff has been requested.
   */
  protected bool $externalEditRequested = FALSE;

  /**
   * Construct a textarea field.
   *
   * @param string $default
   *   The initial value (and live input buffer).
   * @param bool $externalEdit
   *   Whether the external-editor handoff is offered (an available $EDITOR).
   */
  public function __construct(string $default = '', protected bool $externalEdit = FALSE) {
    $this->initTextBuffer($default);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Textarea);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($keys->matches($key, Action::ExternalEdit)) {
      // Only act when the handoff is offered; either way the bound key is
      // swallowed rather than inserting a raw control byte into the buffer.
      if ($this->externalEdit) {
        $this->externalEditRequested = TRUE;
      }

      return;
    }

    if ($keys->matches($key, Action::NewLine)) {
      $this->insert("\n");

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->moveLine(-1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->moveLine(1);

      return;
    }

    if ($this->handleCancel($key)) {
      return;
    }

    // Accept is checked here, after the newline branch, because this scope
    // binds it to Tab rather than Enter.
    if ($this->handleAccept($key)) {
      return;
    }

    $this->handleTextEditKey($key);
  }

  /**
   * Move the cursor to the adjacent line, keeping the column when possible.
   *
   * @param int $delta
   *   The line offset: -1 for up, 1 for down.
   */
  protected function moveLine(int $delta): void {
    $lines = explode("\n", $this->buffer);

    $line = 0;
    $column = $this->cursor;
    foreach ($lines as $index => $text) {
      $length = Strings::length($text);

      if ($column <= $length) {
        $line = $index;
        break;
      }

      // Skip the line and its trailing newline.
      $column -= $length + 1;
    }

    $target = $line + $delta;

    if ($target < 0 || $target >= count($lines)) {
      return;
    }

    $offset = 0;
    for ($index = 0; $index < $target; $index++) {
      $offset += Strings::length($lines[$index]) + 1;
    }

    $this->cursor = $offset + min($column, Strings::length($lines[$target]));
  }

  /**
   * {@inheritdoc}
   */
  public function wantsExternalEdit(): bool {
    return $this->externalEditRequested;
  }

  /**
   * {@inheritdoc}
   *
   * Clears the pending request. A non-NULL buffer replaces the value and is
   * accepted, so saving and exiting the editor commits the field. A NULL buffer
   * (the edit was aborted or unavailable) leaves the inline value untouched.
   */
  public function applyExternalEdit(?string $content): void {
    $this->externalEditRequested = FALSE;

    if ($content === NULL) {
      return;
    }

    $this->initTextBuffer($content);
    $this->accept($this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->renderCaretLine($theme) . $this->placeholderGhost($theme, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    $hints = [new Hint('insert a newline', Action::NewLine), ...parent::hints()];

    if ($this->externalEdit) {
      $hints[] = new Hint('open the editor', Action::ExternalEdit);
    }

    return $hints;
  }

}
