<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Traits;

/**
 * Opens connected socket pairs, which stream_select() can wait on.
 *
 * An in-memory stream has no descriptor to select on, so a test that waits
 * on a stream writes to 1 end of a pair and waits on the other.
 */
trait OpensSocketPairsTrait {

  /**
   * A connected pair of sockets.
   *
   * @return array{resource, resource}
   *   The end read from and the end written to.
   */
  protected function socketPair(): array {
    $domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
    $pair = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    if ($pair === FALSE) {
      $this->fail('Failed to open a socket pair.');
    }

    return [$pair[0], $pair[1]];
  }

}
