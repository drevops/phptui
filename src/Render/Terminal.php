<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Theme\Mode;

/**
 * Thin terminal I/O: raw mode, alternate screen, mouse, size, render, restore.
 *
 * The raw-mode toggling, input reading and size probing touch the real TTY and
 * are excluded from coverage; the output writing is stream-injectable and the
 * size parsing seam-injectable, so both are testable.
 *
 * @package DrevOps\Tui\Render
 */
class Terminal {

  /**
   * The number of background-query polls before giving up.
   */
  protected const int QUERY_POLLS = 3;

  /**
   * The per-poll wait for a background-query reply, in microseconds.
   */
  protected const int QUERY_POLL_INTERVAL_US = 100000;

  /**
   * The width assumed when no probe reports one, in columns.
   */
  protected const int FALLBACK_WIDTH = 80;

  /**
   * The height assumed when no probe reports one, in rows.
   */
  protected const int FALLBACK_HEIGHT = 24;

  /**
   * The output stream.
   *
   * @var resource
   */
  protected $output;

  /**
   * The input stream.
   *
   * @var resource
   */
  protected $input;

  /**
   * The background SGR each rendered frame is washed with, or NULL for none.
   */
  protected ?string $background = NULL;

  /**
   * The probed [columns, rows], detected once per instance; NULL until probed.
   *
   * @var array{int,int}|null
   */
  protected ?array $size = NULL;

  /**
   * Construct a terminal.
   *
   * @param mixed $output
   *   The output stream resource (defaults to STDOUT).
   * @param mixed $input
   *   The input stream resource (defaults to STDIN).
   *
   * @throws \InvalidArgumentException
   *   When a non-NULL argument is not a stream resource - failing loudly
   *   instead of silently rewiring I/O to the real STDOUT/STDIN.
   */
  public function __construct(mixed $output = NULL, mixed $input = NULL) {
    if ($output !== NULL && !is_resource($output)) {
      throw new \InvalidArgumentException('The output stream must be a resource or NULL.');
    }

    if ($input !== NULL && !is_resource($input)) {
      throw new \InvalidArgumentException('The input stream must be a resource or NULL.');
    }

    $this->output = $output ?? STDOUT;
    $this->input = $input ?? STDIN;
  }

  /**
   * Write text to the output.
   *
   * @param string $text
   *   The text.
   */
  public function write(string $text): void {
    fwrite($this->output, $text);
  }

  /**
   * Flush any buffered output so it reaches the terminal immediately.
   *
   * A frame written without a trailing newline can sit in the stream buffer;
   * flushing makes each animation frame appear the moment it is written.
   */
  public function flush(): void {
    fflush($this->output);
  }

  /**
   * Whether the output stream is an interactive terminal.
   *
   * A redirected or piped stream is not a TTY, where cursor and colour control
   * sequences have no effect.
   *
   * @return bool
   *   TRUE when the output is a TTY.
   */
  public function isOutputTty(): bool {
    return stream_isatty($this->output);
  }

  /**
   * Clear the screen and write a frame.
   *
   * @param string $frame
   *   The frame.
   */
  public function render(string $frame): void {
    if ($this->background === NULL) {
      $this->write(TerminalControl::clear() . $frame);

      return;
    }

    // Open the wash before the clear so the erase fills the screen with it,
    // then wash the frame so its gaps and padding keep the background too.
    $open = Ansi::ESC . '[' . $this->background . 'm';
    $this->write($open . TerminalControl::clear() . Ansi::wash($frame, $this->background) . Ansi::ESC . '[0m');
  }

  /**
   * Clear the screen.
   */
  public function clear(): void {
    $this->write(TerminalControl::clear());
  }

  /**
   * Enter the full-screen raw-input mode.
   *
   * @param string|null $background
   *   A background SGR to wash each rendered frame with, or NULL to keep the
   *   terminal's own background.
   */
  public function setup(?string $background = NULL): void {
    // @codeCoverageIgnoreStart
    $this->background = $background;
    // Disable signal keys (-isig) so Ctrl-C arrives as an input byte rather
    // than raising SIGINT, which would kill the process before restore() could
    // leave the alternate screen and undo raw mode. stty sane restores them.
    $this->stty('-echo -icanon -isig');
    $this->write(TerminalControl::altScreenOn() . TerminalControl::hideCursor() . TerminalControl::mouseOn());
    // @codeCoverageIgnoreEnd
  }

  /**
   * Restore the terminal to its normal mode.
   */
  public function restore(): void {
    // @codeCoverageIgnoreStart
    // Reset any wash so the shell's own colours return with the main screen.
    $this->write(Ansi::ESC . '[0m' . TerminalControl::restore());
    $this->stty('sane');
    $this->background = NULL;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Read raw bytes from the input.
   *
   * @param int $bytes
   *   The maximum number of bytes to read.
   *
   * @return string
   *   The bytes read.
   */
  public function read(int $bytes = 32): string {
    $data = fread($this->input, max(1, $bytes));

    return $data === FALSE ? '' : $data;
  }

  /**
   * The terminal height in rows.
   *
   * A LINES environment override wins; otherwise the size is probed from the
   * terminal once per instance, falling back to the classic 24 rows.
   *
   * @return int
   *   The number of rows available for rendering.
   */
  public function height(): int {
    return $this->envDimension('LINES') ?? $this->size()[1];
  }

  /**
   * The terminal width in columns.
   *
   * A COLUMNS environment override wins; otherwise the size is probed from the
   * terminal once per instance, falling back to the classic 80 columns.
   *
   * @return int
   *   The number of columns available for rendering.
   */
  public function width(): int {
    return $this->envDimension('COLUMNS') ?? $this->size()[0];
  }

  /**
   * A positive integer dimension from the environment, or NULL when unset.
   *
   * @param string $name
   *   The variable name (COLUMNS or LINES).
   *
   * @return int|null
   *   The dimension, or NULL when the variable is unset or not a positive
   *   integer.
   */
  protected function envDimension(string $name): ?int {
    $value = getenv($name);

    if (!is_string($value) || !ctype_digit(trim($value))) {
      return NULL;
    }

    $dimension = (int) trim($value);

    return $dimension > 0 ? $dimension : NULL;
  }

  /**
   * The probed [columns, rows], detected once and cached for the instance.
   *
   * @return array{int,int}
   *   The columns and rows, with the 80x24 fallback where probing failed.
   */
  protected function size(): array {
    return $this->size ??= $this->detectSize() ?? [self::FALLBACK_WIDTH, self::FALLBACK_HEIGHT];
  }

  /**
   * Probe the terminal size from the platform's console tooling.
   *
   * @return array{int,int}|null
   *   The [columns, rows], or NULL when nothing reported a size (no TTY, or
   *   the tooling is unavailable).
   */
  protected function detectSize(): ?array {
    if ($this->isWindows()) {
      return self::sizeFromModeCon($this->run('mode CON'));
    }

    return self::sizeFromStty($this->run('stty size 2>/dev/null'));
  }

  /**
   * Whether the platform is Windows, where `stty` gives way to `mode CON`.
   *
   * @return bool
   *   TRUE on Windows.
   */
  protected function isWindows(): bool {
    return DIRECTORY_SEPARATOR === '\\';
  }

  /**
   * Parse a `stty size` reply ("rows cols") into [columns, rows].
   *
   * @param string|null $output
   *   The command output, or NULL when the command produced none.
   *
   * @return array{int,int}|null
   *   The [columns, rows], or NULL when the output is not a size reply.
   */
  public static function sizeFromStty(?string $output): ?array {
    if (!is_string($output) || preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $output, $matches) !== 1) {
      return NULL;
    }

    return [(int) $matches[2], (int) $matches[1]];
  }

  /**
   * Parse a `mode CON` report into [columns, rows].
   *
   * The report labels are localized, so the parse anchors on the dashed rule
   * and reads the first two numbers after it - lines first, then columns.
   *
   * @param string|null $output
   *   The command output, or NULL when the command produced none.
   *
   * @return array{int,int}|null
   *   The [columns, rows], or NULL when the output is not a console report.
   */
  public static function sizeFromModeCon(?string $output): ?array {
    if (!is_string($output) || preg_match('/-+\r?\n\D*(\d+)\D*\r?\n\D*(\d+)/', $output, $matches) !== 1) {
      return NULL;
    }

    return [(int) $matches[2], (int) $matches[1]];
  }

  /**
   * Run a console command and capture its output.
   *
   * The one seam that spawns a process, so tests inject canned probe replies.
   *
   * @param string $command
   *   The command line.
   *
   * @return string|null
   *   The command's output, or NULL when it produced none.
   */
  protected function run(string $command): ?string {
    // @codeCoverageIgnoreStart
    $output = shell_exec($command);

    return is_string($output) ? $output : NULL;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Query the terminal background colour via OSC 11.
   *
   * Writes the query and polls for the reply with stream_select() so a terminal
   * that never answers cannot block, and so a no-reply probe never issues the
   * zero-byte read that would latch EOF on the shared input stream. Leaves the
   * terminal on the main screen.
   *
   * @return string|null
   *   The raw reply bytes, or NULL when the input is not a TTY or no reply
   *   arrived.
   */
  public function queryBackground(): ?string {
    if (!stream_isatty($this->input)) {
      return NULL;
    }

    // @codeCoverageIgnoreStart
    $this->stty('-echo -icanon');
    $response = '';

    try {
      $this->write(TerminalControl::queryBackground());
      fflush($this->output);

      for ($poll = 0; $poll < self::QUERY_POLLS; $poll++) {
        $read = [$this->input];
        $write = [];
        $except = [];
        $ready = stream_select($read, $write, $except, 0, self::QUERY_POLL_INTERVAL_US);

        if ($ready === FALSE || $ready < 1) {
          if ($response !== '') {
            break;
          }

          continue;
        }

        $chunk = fread($this->input, 64);
        if (!is_string($chunk)) {
          continue;
        }
        if ($chunk === '') {
          continue;
        }

        $response .= $chunk;

        if (str_contains($response, "\007") || str_contains($response, Ansi::ESC . '\\')) {
          break;
        }
      }
    }
    finally {
      $this->stty('sane');
    }

    return $response === '' ? NULL : $response;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Detect whether the environment advertises a Unicode-capable locale.
   *
   * The first set of LC_ALL, LC_CTYPE or LANG decides, and a "UTF" locale
   * enables Unicode. An unset locale falls back to ASCII.
   *
   * @return bool
   *   TRUE when a UTF locale is advertised.
   */
  public static function detectUnicode(): bool {
    foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $var) {
      $value = getenv($var);
      if (is_string($value) && $value !== '') {
        return stripos($value, 'utf') !== FALSE;
      }
    }

    return FALSE;
  }

  /**
   * Detect whether the environment supports ANSI colour.
   *
   * Honours the NO_COLOR convention and the "dumb" terminal.
   *
   * @return bool
   *   TRUE unless NO_COLOR is set to a non-empty value or TERM is "dumb".
   */
  public static function detectColor(): bool {
    $no_color = getenv('NO_COLOR');

    // The NO_COLOR convention treats an empty value as unset.
    if (is_string($no_color) && $no_color !== '') {
      return FALSE;
    }

    return getenv('TERM') !== 'dumb';
  }

  /**
   * Detect the colour mode that best matches the terminal background.
   *
   * Resolves in order: the OSC 11 background reply (when one was captured),
   * then the COLORFGBG environment variable, then a dark default.
   *
   * @param string|null $osc_response
   *   The raw OSC 11 reply bytes, or NULL when the terminal was not queried or
   *   did not answer.
   *
   * @return \DrevOps\Tui\Theme\Mode
   *   The detected mode.
   */
  public static function detectMode(?string $osc_response = NULL): Mode {
    if (is_string($osc_response)) {
      $mode = self::modeFromOsc($osc_response);

      if ($mode instanceof Mode) {
        return $mode;
      }
    }

    return self::modeFromColorFgBg() ?? Mode::Dark;
  }

  /**
   * Derive the colour mode from an OSC 11 background-colour reply.
   *
   * A terminal answers with a payload such as "rgb:rrrr/gggg/bbbb" whose three
   * channels each carry 1 to 4 hex digits (an "rgba:" prefix is also seen).
   * The background's relative luminance selects the mode.
   *
   * @param string $response
   *   The raw reply bytes.
   *
   * @return \DrevOps\Tui\Theme\Mode|null
   *   The mode, or NULL when the reply holds no parseable colour.
   */
  protected static function modeFromOsc(string $response): ?Mode {
    if (preg_match('#rgba?:([0-9a-f]{1,4})/([0-9a-f]{1,4})/([0-9a-f]{1,4})#i', $response, $matches) !== 1) {
      return NULL;
    }

    $is_dark = self::luminanceIsDark(self::channel($matches[1]), self::channel($matches[2]), self::channel($matches[3]));

    return $is_dark ? Mode::Dark : Mode::Light;
  }

  /**
   * Scale a 1-4 hex-digit colour channel to the 0-255 range.
   *
   * @param string $hex
   *   The channel as 1 to 4 hexadecimal digits.
   *
   * @return int
   *   The channel value, 0-255.
   */
  protected static function channel(string $hex): int {
    $max = (2 ** (4 * strlen($hex))) - 1;

    return (int) round((int) hexdec($hex) / $max * 255);
  }

  /**
   * Whether an RGB colour reads as a dark background.
   *
   * Uses the Rec. 709 relative-luminance coefficients; a colour below the
   * midpoint of the range is dark.
   *
   * @param int $r
   *   Red, 0-255.
   * @param int $g
   *   Green, 0-255.
   * @param int $b
   *   Blue, 0-255.
   *
   * @return bool
   *   TRUE when the colour is dark.
   */
  protected static function luminanceIsDark(int $r, int $g, int $b): bool {
    return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) < 128;
  }

  /**
   * Derive the colour mode from the COLORFGBG environment variable.
   *
   * Some terminals export "fg;bg" (or "fg;decoration;bg") as palette indices.
   * The background is the last field; indices 0-6 and 8 are the dark half of
   * the standard sixteen-colour palette, the rest light.
   *
   * @return \DrevOps\Tui\Theme\Mode|null
   *   The mode, or NULL when COLORFGBG is unset or its background is not a
   *   palette index.
   */
  protected static function modeFromColorFgBg(): ?Mode {
    $value = getenv('COLORFGBG');

    if (!is_string($value) || $value === '') {
      return NULL;
    }

    $parts = explode(';', $value);
    $background = end($parts);

    if (!ctype_digit($background)) {
      return NULL;
    }

    return in_array((int) $background, [0, 1, 2, 3, 4, 5, 6, 8], TRUE) ? Mode::Dark : Mode::Light;
  }

  /**
   * Run stty with the given arguments.
   *
   * @param string $args
   *   The stty arguments.
   */
  protected function stty(string $args): void {
    // @codeCoverageIgnoreStart
    exec('stty ' . $args . ' 2>/dev/null');
    // @codeCoverageIgnoreEnd
  }

}
