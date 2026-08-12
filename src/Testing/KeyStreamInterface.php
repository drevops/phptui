<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

use DrevOps\PhpTui\Input\Key;

/**
 * A source of key presses consumed by the fields.
 *
 * @package DrevOps\PhpTui\Testing
 */
interface KeyStreamInterface {

  /**
   * Read the next key.
   *
   * @return \DrevOps\PhpTui\Input\Key|null
   *   The next key, or NULL when the stream is exhausted.
   */
  public function read(): ?Key;

}
