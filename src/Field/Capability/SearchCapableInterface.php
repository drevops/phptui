<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A field that presents its query as a search: ranked rows, highlighted hits.
 *
 * {@see SearchCapableTrait} carries the default implementation for the choice
 * fields.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface SearchCapableInterface {

  /**
   * The matched-character positions in a label under the current query.
   *
   * @param string $label
   *   The candidate label.
   *
   * @return list<int>
   *   The matched indices, or an empty list when not searching.
   */
  public function matchPositions(string $label): array;

  /**
   * The query line: the typed query followed by the caret.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The query line.
   */
  public function queryLine(ThemeInterface $theme): string;

}
