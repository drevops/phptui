<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Field\Capability\CompletionCapableInterface;
use DrevOps\Tui\Field\Capability\CompletionCapableTrait;
use DrevOps\Tui\Field\Capability\PagingCapableInterface;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\PlaceholderCapableInterface;
use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableTrait;
use DrevOps\Tui\Field\Capability\SearchCapableInterface;
use DrevOps\Tui\Field\Capability\TextEditCapableInterface;

/**
 * An autocomplete text input fuzzy-filtering a fixed option set.
 *
 * @package DrevOps\Tui\Field
 */
class Suggest extends AbstractField implements SearchCapableInterface, TextEditCapableInterface, QueryOptionsCapableInterface, PagingCapableInterface, PlaceholderCapableInterface, CompletionCapableInterface {

  use PagingCapableTrait;
  use QueryOptionsCapableTrait;
  use PlaceholderCapableTrait;
  use CompletionCapableTrait;

  /**
   * The highlighted suggestion index, or -1 for none.
   */
  protected int $cursor = -1;

  /**
   * The live input buffer.
   */
  protected string $buffer = '';

  /**
   * The buffer the memoized ranking was computed for, or NULL for none yet.
   */
  protected ?string $rankedFor = NULL;

  /**
   * The memoized ranking for {@see $rankedFor}.
   *
   * @var list<string>
   */
  protected array $ranked = [];

  /**
   * Construct a suggest field.
   *
   * @param list<string> $values
   *   The suggestion values.
   * @param string $default
   *   The initial input.
   * @param int|null $page_size
   *   The number of suggestions shown at once before the list pages; NULL uses
   *   the default.
   * @param array<string,string> $descriptions
   *   The description shown for a highlighted suggestion, keyed by value; a
   *   value with no entry shows none.
   * @param bool $ghost
   *   Whether the leading prefix match is previewed as inline ghost-text after
   *   the caret; FALSE leaves the ranked list as the only completion.
   */
  public function __construct(protected array $values, string $default = '', ?int $page_size = NULL, protected array $descriptions = [], protected bool $ghost = FALSE) {
    $this->buffer = $default;
    $this->pageSize = $this->resolvePageSize($page_size);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Suggest);
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

    // Right accepts the ghost-text like Tab; with nothing to complete it is
    // inert, as it is for a suggest field that never opted into ghost-text.
    if ($keys->matches($key, Action::MoveRight) && $this->bestMatch() !== NULL) {
      $this->applyCompletion();

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
   * Whether a completion is offered in the field's current state.
   *
   * The buffer is append-only, so the caret is always at its end; what gates a
   * completion here is what the rest of the editor is saying. Once a suggestion
   * is highlighted it, not the buffer, is the live value, so previewing a
   * completion of the buffer would contradict it. While a query is in flight
   * the candidates still held are the previous query's, and the list they came
   * from has already been replaced by the loading indicator - previewing one of
   * them would put back the very answer the field is withdrawing.
   *
   * @return bool
   *   TRUE when the ghost-text preview applies.
   */
  protected function completionAvailable(): bool {
    return $this->ghost && $this->cursor < 0 && !$this->queryLoading;
  }

  /**
   * The candidates the buffer is completed against.
   *
   * Drawn from the displayed list rather than the declared order, so the
   * previewed completion is always the leading prefix match of the very list
   * shown beneath it - whether that order came from local ranking or from a
   * query source.
   *
   * @return list<string>
   *   The suggestion values in display order.
   */
  protected function completionCandidates(): array {
    return $this->visible();
  }

  /**
   * Land an accepted completion in the query.
   *
   * The completion is a new query, not a selection: the list re-filters around
   * it and stays open, with nothing highlighted.
   *
   * @param string $match
   *   The candidate to complete the query to.
   */
  protected function completeBuffer(string $match): void {
    $this->buffer = $match;
    $this->resetFilterCursor();
  }

  /**
   * The suggestions matching the current buffer, ranked by fuzzy relevance.
   *
   * Suggestions that came from a query source are already the answer to the
   * buffer, so ranking them again locally would drop the ones that do not
   * literally match it.
   *
   * Only the locally ranked path is memoized, and deliberately so: there the
   * values are fixed for the field's life, so the query alone determines the
   * ranking and one pass serves the several reads a frame makes - the list, the
   * highlighted description, the live value and the ghost-text preview. A query
   * source replaces the values as each query settles, which a query-keyed
   * memo could not see, so that path reads them directly every time.
   *
   * @return list<string>
   *   The matching suggestion values, most relevant first.
   */
  protected function visible(): array {
    if ($this->buffer === '' || $this->queryDriven) {
      return $this->values;
    }

    if ($this->rankedFor === $this->buffer) {
      return $this->ranked;
    }

    $this->rankedFor = $this->buffer;

    return $this->ranked = $this->matcher()->rankValues($this->values, $this->buffer);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): string {
    return $this->buffer;
  }

  /**
   * {@inheritdoc}
   */
  protected function adoptQueryRows(array $rows): void {
    $this->values = Option::selectableValues($rows);

    $this->descriptions = [];
    foreach ($rows as $row) {
      if ($row->selectable()) {
        $this->descriptions[$row->value] = $row->description;
      }
    }

    $this->resetFilterCursor();
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
    $state = $this->queryStateLine($theme);
    if ($state !== NULL) {
      return $this->queryLine($theme) . "\n" . $state;
    }

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
   *
   * The completion suffix and the placeholder share the one ghost slot after
   * the caret: the former needs a typed query to complete, the latter an empty
   * one, so at most one of them is ever set.
   */
  public function queryLine(ThemeInterface $theme): string {
    $completion = $this->ghostSuffix();

    return $this->buffer . $theme->caret() . ($completion === '' ? $this->placeholderGhost($theme, $this->buffer) : $theme->ghost($completion));
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
