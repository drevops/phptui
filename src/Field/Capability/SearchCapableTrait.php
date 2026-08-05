<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Fuzzy-search presentation over a filtered choice field.
 *
 * Ranks the filtered rows by fuzzy relevance, highlights the matched
 * characters, and draws the typed query as a search line.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait SearchCapableTrait {

  /**
   * Rank the option rows by fuzzy relevance to the query.
   *
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Block\Option>
   *   The matching option rows, most relevant first.
   */
  protected function filterOptions(string $needle): array {
    return $this->matcher()->rankOptions($this->options, $needle);
  }

  /**
   * The matched-character positions in a label under the current filter.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices, or an empty list when not filtering.
   */
  public function matchPositions(string $label): array {
    return $this->filter === '' ? [] : $this->matcher()->positions($label, $this->filter);
  }

  /**
   * The query line: the typed filter followed by the caret.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The query line.
   */
  public function queryLine(ThemeInterface $theme): string {
    $elements = $this->elements($theme);

    return $elements->fieldDraft($this->filter) . $elements->fieldCaret();
  }

}
