<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Terminal;

use DrevOps\PhpTui\Terminal\Terminal;

/**
 * Test fixture: a terminal whose size probe returns a canned reply.
 *
 * Overrides the size-probing seams - the console command spawn and the
 * platform test - and the signal availability test, so the size resolution is
 * exercised deterministically on any machine, without spawning processes.
 *
 * @package DrevOps\PhpTui\Tests\Fixtures\Terminal
 */
final class ProbeTerminal extends Terminal {

  /**
   * Construct a probe terminal.
   *
   * @param string|null $reply
   *   The canned probe reply, or NULL for a probe that reports nothing.
   * @param bool $windows
   *   Whether the terminal behaves as if on Windows (probing `mode CON`).
   * @param bool $signals
   *   Whether SIGWINCH can be listened for, where the platform allows it.
   * @param mixed $input
   *   The input stream resource, or NULL for STDIN.
   */
  public function __construct(protected ?string $reply = NULL, protected bool $windows = FALSE, protected bool $signals = TRUE, mixed $input = NULL) {
    parent::__construct(NULL, $input);
  }

  /**
   * Change the canned probe reply, as a resized terminal would.
   *
   * @param string|null $reply
   *   The new reply.
   */
  public function answer(?string $reply): void {
    $this->reply = $reply;
  }

  /**
   * Whether a SIGWINCH handler is installed.
   *
   * @return bool
   *   TRUE while watchResize() is listening.
   */
  public function isWatching(): bool {
    return $this->watching;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function run(string $command): ?string {
    return $this->reply;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function isWindows(): bool {
    return $this->windows;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function isSignalAvailable(): bool {
    return $this->signals && parent::isSignalAvailable();
  }

}
