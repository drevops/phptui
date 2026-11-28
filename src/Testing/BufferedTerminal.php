<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

use DrevOps\PhpTui\Terminal\Terminal;
use DrevOps\PhpTui\Terminal\Wake;

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
 * The streams a wait or an idle is given are polled for real, without
 * waiting, before the script is read: a stream with output ends the wait
 * ahead of the next scripted item, so a test writes to a socket pair to wake
 * the session.
 *
 * The terminal can be suspended unless told otherwise. A
 * {@see \DrevOps\PhpTui\Testing\Background} right after the suspend key
 * continues the session in the background, where idle() consumes the ticks
 * and resizes that follow; any other scripted item hands the terminal back, as
 * the shell's `fg` does.
 *
 * @package DrevOps\PhpTui\Testing
 */
final class BufferedTerminal extends Terminal {

  /**
   * The background colour passed to setup(), captured for paint-wiring tests.
   */
  public ?string $paintedBackground = NULL;

  /**
   * The number of times the session suspended the terminal.
   */
  public int $suspensions = 0;

  /**
   * Whether the terminal can be suspended, as one with job control can.
   */
  public bool $suspendable = TRUE;

  /**
   * Whether a scripted resize changed the size since remeasure() last ran.
   */
  protected bool $resized = FALSE;

  /**
   * Whether the process holds the terminal rather than running in the
   * background.
   */
  protected bool $foreground = TRUE;

  /**
   * Construct a buffered terminal.
   *
   * @param list<string|\DrevOps\PhpTui\Testing\Tick|\DrevOps\PhpTui\Testing\Resize|\DrevOps\PhpTui\Testing\Background> $keystrokes
   *   The keystroke byte sequences to deliver, one per read(), with the
   *   timeouts, resizes and background continues that fall between them.
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
  public function wait(int $microseconds, array $streams = []): Wake {
    $ready = $this->select($streams, 0);

    if ($ready !== []) {
      return new Wake(FALSE, $ready);
    }

    $next = $this->keystrokes[0] ?? NULL;

    if ($next instanceof Resize) {
      array_shift($this->keystrokes);
      $this->apply($next);

      return new Wake(FALSE);
    }

    if ($next instanceof Tick) {
      array_shift($this->keystrokes);

      return new Wake(FALSE);
    }

    return new Wake(TRUE);
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
   * {@inheritdoc}
   */
  #[\Override]
  public function isSuspendable(): bool {
    return $this->suspendable;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function suspend(): void {
    $this->suspensions++;
    $this->foreground = !(($this->keystrokes[0] ?? NULL) instanceof Background);

    if (!$this->foreground) {
      array_shift($this->keystrokes);
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function reclaim(): bool {
    return $this->foreground;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function idle(int $microseconds, array $streams = []): array {
    $ready = $this->select($streams, 0);

    if ($ready !== []) {
      return $ready;
    }

    $next = $this->keystrokes[0] ?? NULL;

    if ($next instanceof Tick) {
      array_shift($this->keystrokes);

      return [];
    }

    if ($next instanceof Resize) {
      array_shift($this->keystrokes);
      $this->apply($next);

      return [];
    }

    $this->foreground = TRUE;

    return [];
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
