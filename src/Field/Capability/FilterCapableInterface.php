<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field whose visible rows narrow under a typed query.
 *
 * The match strategy (substring, fuzzy, per-directory) is the field's own;
 * {@see FilterCapableTrait} carries the default implementation for the choice
 * fields.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface FilterCapableInterface {

  /**
   * The current type-to-filter text.
   *
   * @return string
   *   The live query, empty when not filtering.
   */
  public function filter(): string;

  /**
   * Move the cursor to the first match and reset paging on a query change.
   */
  public function resetFilterCursor(): void;

}
