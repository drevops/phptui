<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field that windows a long list to a page that follows the cursor.
 *
 * {@see PagingCapableTrait} carries the default implementation.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface PagingCapableInterface {

  /**
   * The number of rows shown at once before the list pages.
   *
   * @return int
   *   The effective page size.
   */
  public function pageSize(): int;

}
