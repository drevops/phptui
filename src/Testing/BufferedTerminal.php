<?php

declare(strict_types=1);

namespace DrevOps\Tui\Testing;

use DrevOps\Tui\Terminal\Terminal;

/**
 * A terminal that feeds scripted keystrokes and captures rendered output.
 *
 * Stands in for a real TTY: each read() returns the next scripted keystroke's
 * bytes - one keypress at a time, as a real terminal delivers them - and
 * everything the panel loop renders is captured for later assertions. The
 * raw-mode and alternate-screen calls are no-ops because there is no TTY to
 * switch, and the height is fixed so rendered frames are deterministic.
 *
 * @package DrevOps\Tui\Testing
 */
final class BufferedTerminal extends Terminal {

  /**
   * The background colour passed to setup(), captured for paint-wiring tests.
   */
  public ?string $paintedBackground = NULL;

  /**
   * Construct a buffered terminal.
   *
   * @param list<string> $keystrokes
   *   The keystroke byte sequences to deliver, one per read().
   * @param int $rows
   *   The reported terminal height, fixed so rendered frames do not depend on
   *   the machine running the test.
   * @param int $cols
   *   The reported terminal width, fixed for the same reason.
   */
  public function __construct(protected array $keystrokes = [], protected int $rows = 24, protected int $cols = 80) {
    $output = fopen('php://memory', 'r+');
    $input = fopen('php://memory', 'r+');

    if ($output === FALSE || $input === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Failed to open an in-memory stream.');
      // @codeCoverageIgnoreEnd
    }

    parent::__construct($output, $input);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function setup(?string $background = NULL): void {
    $this->paintedBackground = $background;
    $this->background = $background;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function restore(): void {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function read(int $bytes = 32): string {
    return array_shift($this->keystrokes) ?? '';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function rows(): int {
    return $this->rows;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function columns(): int {
    return $this->cols;
  }

  /**
   * Everything written to the terminal since construction.
   *
   * @return string
   *   The captured output: all rendered frames and control sequences.
   */
  public function output(): string {
    rewind($this->output);

    return (string) stream_get_contents($this->output);
  }

}
