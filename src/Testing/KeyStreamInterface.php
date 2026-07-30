<?php

declare(strict_types=1);

namespace DrevOps\Tui\Testing;

use DrevOps\Tui\Input\Key;

/**
 * A source of key presses consumed by the fields.
 *
 * @package DrevOps\Tui\Testing
 */
interface KeyStreamInterface {

  /**
   * Read the next key.
   *
   * @return \DrevOps\Tui\Input\Key|null
   *   The next key, or NULL when the stream is exhausted.
   */
  public function read(): ?Key;

}
