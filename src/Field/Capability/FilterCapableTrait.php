<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Utils\Strings;

/**
 * Type-to-filter behaviour over a choice field's option rows.
 *
 * Printable characters narrow the visible rows to the matching options and
 * Backspace widens them again; the match strategy itself (substring, fuzzy)
 * is the field's own.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait FilterCapableTrait {

  /**
   * The current type-to-filter text.
   */
  protected string $filter = '';

  /**
   * The current type-to-filter text.
   *
   * @return string
   *   The live query, empty when not filtering.
   */
  public function filter(): string {
    return $this->filter;
  }

  /**
   * Filter the option rows to those matching the query.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The matching option rows.
   */
  abstract protected function filterOptions(string $needle): array;

  /**
   * Handle a filter edit: Backspace deletes a character, a printable appends.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key edited the filter.
   */
  protected function handleFilterKey(Key $key): bool {
    if ($this->keys()->matches($key, Action::DeleteBack)) {
      $this->filter = Strings::substr($this->filter, 0, -1);
      $this->resetFilterCursor();

      return TRUE;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->resetFilterCursor();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Land the cursor on the first match and reset paging on a query change.
   */
  public function resetFilterCursor(): void {
    $this->cursor = $this->firstSelectable($this->visible());
    $this->offset = 0;
  }

  /**
   * The rows currently visible under the filter.
   *
   * With no filter every row shows in declared order; once filtering, only
   * matching options show - structural headings and separators drop away so
   * the result reads as a flat list. Rows that came from a query source are
   * already the answer to the query, so filtering them again locally would drop
   * the ones whose labels do not literally match it.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The visible rows.
   */
  public function visible(): array {
    if ($this->filter === '' || ($this instanceof QueryOptionsCapableInterface && $this->isQueryDriven())) {
      return $this->options;
    }

    return $this->filterOptions($this->filter);
  }

}
