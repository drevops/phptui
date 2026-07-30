<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Capability;

/**
 * A block that does something when it is activated.
 *
 * Activating it acts rather than reveals: work runs, or the form ends. Nothing
 * it does reaches the collected result, which is what separates it from the
 * block that holds a value.
 *
 * @package DrevOps\Tui\Block\Capability
 */
interface ActivateCapableInterface {

  /**
   * Do what activating this block does.
   *
   * @return bool
   *   Whether anything was done.
   */
  public function activate(): bool;

}
