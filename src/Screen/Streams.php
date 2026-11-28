<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * The streams a session waits on beside the keyboard, each with a callback.
 *
 * A session waiting for a key also wakes when a watched stream has output,
 * and runs that stream's callback before it draws the next frame. The callback
 * reads what arrived. A stream that is closed, or at its end once the callback
 * has run, is no longer watched, so a finished child process cannot keep the
 * session awake.
 *
 * The same instance is handed to the session, so a stream watched or unwatched
 * while the session runs - from a callback or a tick - applies on the next
 * wait.
 *
 * @package DrevOps\PhpTui\Screen
 */
final class Streams {

  /**
   * The watched streams, keyed by resource id.
   *
   * @var array<int, resource>
   */
  protected array $streams = [];

  /**
   * The callback for each watched stream, keyed by resource id.
   *
   * @var array<int, \Closure(resource): void>
   */
  protected array $callbacks = [];

  /**
   * Watch a stream, replacing any callback it was watched with.
   *
   * @param mixed $stream
   *   An open stream resource, such as a pipe from proc_open().
   * @param \Closure(resource): void $callback
   *   What runs when the stream has output, given the stream to read from.
   *
   * @return $this
   *   The registry.
   *
   * @throws \InvalidArgumentException
   *   When the stream is not an open stream resource.
   */
  public function watch(mixed $stream, \Closure $callback): static {
    if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
      throw new \InvalidArgumentException('A watched stream must be an open stream resource.');
    }

    $id = get_resource_id($stream);
    $this->streams[$id] = $stream;
    $this->callbacks[$id] = $callback;

    return $this;
  }

  /**
   * Stop watching a stream.
   *
   * @param mixed $stream
   *   The stream, open or already closed; one that is not watched is ignored.
   *
   * @return $this
   *   The registry.
   *
   * @throws \InvalidArgumentException
   *   When the value is not a resource at all.
   */
  public function unwatch(mixed $stream): static {
    // A closed resource is no longer reported by is_resource().
    if (!is_resource($stream) && gettype($stream) !== 'resource (closed)') {
      throw new \InvalidArgumentException('An unwatched stream must be a stream resource.');
    }

    /** @var resource $stream */
    $id = get_resource_id($stream);
    unset($this->streams[$id], $this->callbacks[$id]);

    return $this;
  }

  /**
   * The streams to wait on, without the ones closed since they were watched.
   *
   * @return array<int, resource>
   *   The open streams, keyed by resource id.
   */
  public function streams(): array {
    foreach ($this->streams as $id => $stream) {
      if (!is_resource($stream)) {
        unset($this->streams[$id], $this->callbacks[$id]);
      }
    }

    return $this->streams;
  }

  /**
   * Run the callback of each stream that has output.
   *
   * @param array<array-key, resource> $ready
   *   The streams with output, as a wait reported them.
   */
  public function dispatch(array $ready): void {
    foreach ($ready as $stream) {
      $id = get_resource_id($stream);
      // An earlier callback in the same pass can unwatch this stream.
      $callback = $this->callbacks[$id] ?? NULL;

      if (!$callback instanceof \Closure) {
        continue;
      }

      $callback($stream);

      // A stream at its end stays readable, so it would wake every wait.
      if (!is_resource($stream) || feof($stream)) {
        unset($this->streams[$id], $this->callbacks[$id]);
      }
    }
  }

}
