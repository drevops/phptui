<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Capability;

/**
 * A block that can reject what you gave it, and say why.
 *
 * Refusing and holding are separate jobs: a block can refuse a value without
 * ever holding one, and only what is held reaches the result.
 *
 * @package DrevOps\PhpTui\Block\Capability
 */
interface RejectCapableInterface {

  /**
   * Why what was given was refused.
   *
   * @return string|null
   *   The reason, or NULL when nothing is refused.
   */
  public function refusal(): ?string;

}
