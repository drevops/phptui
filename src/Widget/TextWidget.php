<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Widget\Capability\CompletionCapableInterface;
use DrevOps\Tui\Widget\Capability\CompletionCapableTrait;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableInterface;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;

/**
 * Single-line text input with a movable cursor and optional ghost-text.
 *
 * @package DrevOps\Tui\Widget
 */
class TextWidget extends AbstractWidget implements TextEditCapableInterface, CompletionCapableInterface, PlaceholderCapableInterface {

  use TextEditCapableTrait;
  use CompletionCapableTrait;
  use PlaceholderCapableTrait;

  /**
   * Construct a text widget.
   *
   * @param string $default
   *   The initial value (and live input buffer).
   * @param list<string> $completions
   *   Inline ghost-text candidates: the buffer is completed to the first
   *   candidate it is a prefix of. Empty leaves a plain text field.
   */
  public function __construct(string $default = '', protected array $completions = []) {
    $this->initTextBuffer($default);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Text);
  }

  /**
   * The candidates the buffer is completed against.
   *
   * @return list<string>
   *   The declared candidates, in declaration order.
   */
  protected function completionCandidates(): array {
    return $this->completions;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    if ($keys->matches($key, Action::Complete)) {
      $this->applyCompletion();

      return;
    }

    // At the line's end, Right accepts the ghost-text like Tab; elsewhere it
    // falls through to the plain caret move.
    if ($keys->matches($key, Action::MoveRight) && $this->bestMatch() !== NULL) {
      $this->applyCompletion();

      return;
    }

    $this->handleTextEditKey($key);
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    return $this->caretLine($theme);
  }

  /**
   * Render the input line with the caret and any inline ghost-text.
   *
   * The completion suffix and the placeholder share the one ghost slot: the
   * former needs a typed prefix to complete, the latter an empty buffer, so at
   * most one of them is ever set.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the caret glyph and the ghost styling.
   *
   * @return string
   *   The input line.
   */
  protected function caretLine(ThemeInterface $theme): string {
    $completion = $this->ghostSuffix();

    return $this->renderInputLine($theme, $completion === '' ? $this->placeholderText($this->buffer) : $completion);
  }

}
