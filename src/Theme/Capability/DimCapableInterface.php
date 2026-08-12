<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Capability;

/**
 * A theme that can push text back behind something drawn over it.
 *
 * Declaring this is what lets a driver float one thing over another and have
 * what is behind read as still there but not in play - rather than either
 * blanking it, which loses the reader's place, or leaving it at full weight,
 * which leaves two things competing for the eye.
 *
 * @package DrevOps\PhpTui\Theme\Capability
 */
interface DimCapableInterface {

  /**
   * Push text back, so what is drawn over it reads as being in front.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The receded text.
   */
  public function dim(string $text): string;

}
