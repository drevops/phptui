<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Terminal;

use DrevOps\PhpTui\Terminal\Terminal;

/**
 * Test fixture: a terminal whose size probe returns a canned reply.
 *
 * Overrides the two size-probing seams - the console command spawn and the
 * platform test - so the size resolution is exercised deterministically on any
 * machine, without spawning processes.
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
   */
  public function __construct(protected ?string $reply = NULL, protected bool $windows = FALSE) {
    parent::__construct();
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

}
