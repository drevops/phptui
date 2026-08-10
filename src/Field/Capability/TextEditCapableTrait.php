<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;

/**
 * Character-buffer editing: a movable cursor, insertion and deletion.
 *
 * All arithmetic is by character, not byte, so multibyte input edits and
 * renders correctly.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait TextEditCapableTrait {

  /**
   * The live input buffer.
   */
  protected string $buffer = '';

  /**
   * The cursor offset within the buffer, in characters.
   */
  protected int $cursor = 0;

  /**
   * Seed the buffer and land the cursor at its end.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   */
  protected function initTextBuffer(string $buffer): void {
    $this->buffer = Ansi::sanitize($buffer);
    $this->cursor = Strings::length($this->buffer);
  }

  /**
   * The live input buffer.
   *
   * @return string
   *   The buffer.
   */
  public function buffer(): string {
    return $this->buffer;
  }

  /**
   * Handle a buffer edit: deletion, cursor movement, insertion.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key edited the buffer or moved the cursor.
   */
  protected function handleTextEditKey(Key $key): bool {
    $keys = $this->keys();

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->backspace();

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveLeft)) {
      $this->cursor = max(0, $this->cursor - 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveRight)) {
      $this->cursor = min(Strings::length($this->buffer), $this->cursor + 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->insert(' ');

      return TRUE;
    }

    if ($key->isChar()) {
      $this->insert($key->char ?? '');

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Insert text at the cursor.
   *
   * A control byte with no binding on it arrives here as an ordinary typed
   * character - a paste carries whole sequences that way - so what is inserted
   * is filtered rather than trusted to be printable.
   *
   * @param string $text
   *   The text to insert.
   */
  public function insert(string $text): void {
    $text = Ansi::sanitize($text);
    $this->buffer = Strings::substr($this->buffer, 0, $this->cursor) . $text . Strings::substr($this->buffer, $this->cursor);
    $this->cursor += Strings::length($text);
  }

  /**
   * Delete the character before the cursor.
   */
  public function backspace(): void {
    if ($this->cursor > 0) {
      $this->buffer = Strings::substr($this->buffer, 0, $this->cursor - 1) . Strings::substr($this->buffer, $this->cursor);
      $this->cursor--;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->buffer;
  }

  /**
   * The buffer split into the text before and after the caret.
   *
   * @return array{string,string}
   *   The before-caret and after-caret segments.
   */
  protected function caretSegments(): array {
    return [
      Strings::substr($this->buffer, 0, $this->cursor),
      Strings::substr($this->buffer, $this->cursor),
    ];
  }

  /**
   * Render the buffer split by the caret at the cursor position.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the caret glyph.
   *
   * @return string
   *   The input line.
   */
  protected function renderCaretLine(ThemeInterface $theme): string {
    [$before, $after] = $this->caretSegments();
    $elements = $this->elements($theme);

    return $elements->fieldDraft($before) . $elements->fieldCaret() . $elements->fieldDraft($after);
  }

  /**
   * Render the buffer as a single-line input field, in the theme's field style.
   *
   * The flat style is the plain caret line; the boxed and underline styles fill
   * or underline the field behind the value.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme composing the input.
   * @param string $ghost
   *   The inline ghost-text completion suffix, or an empty string.
   *
   * @return string
   *   The input line.
   */
  protected function renderInputLine(ThemeInterface $theme, string $ghost = ''): string {
    [$before, $after] = $this->caretSegments();

    return $this->elements($theme)->fieldInput($before, $after, $ghost);
  }

}
