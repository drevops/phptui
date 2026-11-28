<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

use DrevOps\PhpTui\Terminal\Terminal;

/**
 * A terminal that feeds scripted keystrokes and captures rendered output.
 *
 * Stands in for a real TTY: each read() returns the next scripted keystroke's
 * bytes - one keypress at a time, as a real terminal delivers them - and
 * everything the panel loop renders is captured for later assertions. The
 * raw-mode and alternate-screen calls are no-ops because there is no TTY to
 * switch, and the height is fixed so rendered frames are deterministic.
 *
 * A {@see \DrevOps\PhpTui\Testing\Tick} in the script makes the next wait()
 * time out, and a {@see \DrevOps\PhpTui\Testing\Resize} makes the terminal
 * report a new size from then on and interrupts the wait it reaches, as a
 * SIGWINCH does. A plain read() skips both, applying any resize on the way.
 *
 * @package DrevOps\PhpTui\Testing
 */
final class BufferedTerminal extends Terminal {

  /**
   * The background colour passed to setup(), captured for paint-wiring tests.
   */
  public ?string $paintedBackground = NULL;

  /**
   * Whether a scripted resize changed the size since remeasure() last ran.
   */
  protected bool $resized = FALSE;

  /**
   * Construct a buffered terminal.
   *
   * @param list<string|\DrevOps\PhpTui\Testing\Tick|\DrevOps\PhpTui\Testing\Resize> $keystrokes
   *   The keystroke byte sequences to deliver, one per read(), with the
   *   timeouts and resizes that fall between them.
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
    while (($next = array_shift($this->keystrokes)) !== NULL) {
      if (is_string($next)) {
        return $next;
      }

      if ($next instanceof Resize) {
        $this->apply($next);
      }
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function wait(int $microseconds): bool {
    $next = $this->keystrokes[0] ?? NULL;

    if ($next instanceof Resize) {
      array_shift($this->keystrokes);
      $this->apply($next);

      return FALSE;
    }

    if ($next instanceof Tick) {
      array_shift($this->keystrokes);

      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function watchResize(): void {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function unwatchResize(): void {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function remeasure(): bool {
    $resized = $this->resized;
    $this->resized = FALSE;

    return $resized;
  }

  /**
   * Report the size a scripted resize states from now on.
   *
   * @param \DrevOps\PhpTui\Testing\Resize $resize
   *   The resize.
   */
  protected function apply(Resize $resize): void {
    $this->resized = $this->resized || $resize->columns !== $this->cols || $resize->rows !== $this->rows;
    $this->cols = $resize->columns;
    $this->rows = $resize->rows;
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
