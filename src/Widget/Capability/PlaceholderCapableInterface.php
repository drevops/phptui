<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

/**
 * Ghost text shown inside an editor while its input is empty.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
interface PlaceholderCapableInterface {

  /**
   * Set the ghost text shown while the input is empty.
   *
   * @param string $placeholder
   *   The placeholder text; empty shows none.
   *
   * @return static
   *   The widget.
   */
  public function setPlaceholder(string $placeholder): static;

}
