<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Capability;

/**
 * A block that does something when it is activated.
 *
 * Activating runs work or ends the form; it reveals nothing. Nothing it does
 * reaches the collected result, so holding a value is a separate capability.
 *
 * @package DrevOps\PhpTui\Block\Capability
 */
interface ActivateCapableInterface {

  /**
   * Perform this block's action.
   *
   * @return bool
   *   Whether anything was done.
   */
  public function activate(): bool;

}
