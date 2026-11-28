<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Terminal;

/**
 * What ended a terminal's wait: a key, output on a stream, or neither.
 *
 * Neither means the wait timed out or a signal interrupted it.
 *
 * @package DrevOps\PhpTui\Terminal
 */
final readonly class Wake {

  /**
   * Construct a wake.
   *
   * @param bool $input
   *   Whether input is ready to read.
   * @param array<array-key, resource> $streams
   *   The streams the wait was given that have output to read, keyed as given.
   */
  public function __construct(public bool $input, public array $streams = []) {
  }

}
