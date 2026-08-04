<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

/**
 * Ghost text shown inside an editor while its input is empty.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface PlaceholderCapableInterface {

  /**
   * Set the ghost text shown while the input is empty.
   *
   * @param string $placeholder
   *   The placeholder text; empty shows none.
   *
   * @return static
   *   The field.
   */
  public function setPlaceholder(string $placeholder): static;

}
