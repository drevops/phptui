<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SearchCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;

/**
 * An autocomplete text input fuzzy-filtering a fixed option set.
 *
 * @package DrevOps\Tui\Widget
 */
class SuggestWidget extends AbstractWidget implements SearchCapableInterface, TextEditCapableInterface, PagingCapableInterface {

  use PagingCapableTrait;

  /**
   * The highlighted suggestion index, or -1 for none.
   */
  protected int $cursor = -1;

  /**
   * Construct a suggest widget.
   *
   * @param list<string> $values
   *   The suggestion values.
   * @param string $buffer
   *   The initial input.
   * @param int|null $page_size
   *   The number of suggestions shown at once before the list pages; NULL uses
   *   the default.
   * @param array<string,string> $descriptions
   *   The description shown for a highlighted suggestion, keyed by value; a
   *   value with no entry shows none.
   */
  public function __construct(protected array $values, protected string $buffer = '', ?int $page_size = NULL, protected array $descriptions = []) {
    $this->pageSize = $this->resolvePageSize($page_size);
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

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = min(count($this->visible()) - 1, $this->cursor + 1);

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = max(-1, $this->cursor - 1);

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->backspace();

      return;
    }

    if ($keys->matches($key, Action::InsertSpace)) {
      $this->insert(' ');

      return;
    }

    if ($key->isChar()) {
      $this->insert($key->char ?? '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buffer(): string {
    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   *
   * The buffer is append-only - the query grows at its end - so the text is
   * added there and the suggestion highlight resets.
   */
  public function insert(string $text): void {
    $this->buffer .= $text;
    $this->resetFilterCursor();
  }

  /**
   * {@inheritdoc}
   */
  public function backspace(): void {
    $this->buffer = Strings::substr($this->buffer, 0, -1);
    $this->resetFilterCursor();
  }

  /**
   * Reset the highlight and paging when the query changes.
   */
  protected function resetFilterCursor(): void {
    $this->cursor = -1;
    $this->offset = 0;
  }

  /**
   * The suggestions matching the current buffer, ranked by fuzzy relevance.
   *
   * @return list<string>
   *   The matching suggestion values, most relevant first.
   */
  protected function visible(): array {
    if ($this->buffer === '') {
      return $this->values;
    }

    return $this->matcher()->rankValues($this->values, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    if ($this->cursor >= 0) {
      $visible = $this->visible();

      return $visible[$this->cursor] ?? $this->buffer;
    }

    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    $rows = [];

    foreach (array_slice($visible, $viewport->offset, $this->pageSize) as $slot => $value) {
      $current = $viewport->offset + $slot === $this->cursor;
      $rows[] = $theme->marker($current) . ' ' . $this->renderMatchedLabel($theme, $value, $this->matchPositions($value), $current);
    }

    return implode("\n", [$this->queryLine($theme), ...$this->wrapScrolled($theme, $rows, $viewport)]);
  }

  /**
   * The description of the highlighted suggestion, empty when none is active.
   *
   * @return string
   *   The highlighted suggestion's description.
   */
  #[\Override]
  protected function highlightedDescription(): string {
    if ($this->cursor < 0) {
      return '';
    }

    $visible = $this->visible();

    return $this->descriptions[$visible[$this->cursor] ?? ''] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function queryLine(ThemeInterface $theme): string {
    return $this->buffer . $theme->caret();
  }

  /**
   * {@inheritdoc}
   */
  public function matchPositions(string $label): array {
    return $this->buffer === '' ? [] : $this->matcher()->positions($label, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function hints(): array {
    return [new Hint('move', Action::MoveUp, Action::MoveDown), ...parent::hints()];
  }

}
