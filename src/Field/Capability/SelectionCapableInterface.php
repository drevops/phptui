<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field whose cursor picks exactly one option as its value.
 *
 * {@see SelectionCapableTrait} carries the default implementation.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface SelectionCapableInterface {

  /**
   * Whether the highlighted visible row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  public function isCurrentSelectable(): bool;

}
