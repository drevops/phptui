<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Fixtures\Terminal;

use DrevOps\PhpTui\Terminal\Terminal;

/**
 * A terminal double that records suspend/restore without touching a TTY.
 */
class RecordingTerminal extends Terminal {

  /**
   * Whether restore() (suspend) was called.
   */
  public bool $restored = FALSE;

  /**
   * Whether setup() (resume) was called.
   */
  public bool $resumed = FALSE;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function restore(): void {
    $this->restored = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function setup(?string $background = NULL): void {
    $this->resumed = TRUE;
  }

}
