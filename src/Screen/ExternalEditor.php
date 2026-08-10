<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Terminal\Terminal;

/**
 * Hands a text buffer off to the user's $EDITOR and captures the result.
 *
 * The buffer is written to a temp file, the terminal is suspended (leaving raw
 * mode and the alt screen so the editor drives the TTY), then the editor runs.
 * On return the terminal is restored and the saved file is read back. The
 * process spawn is the one seam that touches the real TTY and is excluded from
 * coverage; everything around it is testable.
 *
 * @package DrevOps\Tui\Screen
 */
class ExternalEditor {

  /**
   * Resolve the editor command from the environment.
   *
   * $VISUAL wins over $EDITOR, matching the long-standing Unix convention that
   * $VISUAL names a full-screen editor and $EDITOR a line editor.
   *
   * @return string|null
   *   The command (with any arguments), or NULL when neither variable is set to
   *   a non-blank value.
   */
  public function command(): ?string {
    foreach (['VISUAL', 'EDITOR'] as $var) {
      $value = getenv($var);

      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }
    }

    return NULL;
  }

  /**
   * Whether an external editor can be launched.
   *
   * @return bool
   *   TRUE when a command resolves from the environment.
   */
  public function isAvailable(): bool {
    return $this->command() !== NULL;
  }

  /**
   * Open the editor seeded with a buffer and capture the saved result.
   *
   * @param string $initial
   *   The buffer the editor opens with.
   * @param \DrevOps\Tui\Terminal\Terminal|null $terminal
   *   The terminal to suspend around the editor, or NULL to leave terminal
   *   state untouched (the editor still runs).
   *
   * @return string|null
   *   The saved buffer, or NULL when no editor is available or the editor
   *   exited non-zero (an aborted edit) - the caller keeps the inline value.
   */
  public function edit(string $initial, ?Terminal $terminal = NULL): ?string {
    $command = $this->command();

    if ($command === NULL) {
      return NULL;
    }

    $file = $this->tempFile();

    if ($file === NULL) {
      return NULL;
    }

    try {
      if (!$this->seed($file, $initial)) {
        return NULL;
      }

      $terminal?->restore();

      try {
        $code = $this->spawn($command, $file);
      }
      finally {
        // Restore the TUI even if the spawn throws, so a failed launch never
        // strands the terminal in raw mode.
        $terminal?->setup();
      }

      if ($code !== 0) {
        return NULL;
      }

      // Guard the read: a file the editor deleted counts as an aborted edit,
      // not a fatal error.
      $content = is_file($file) ? file_get_contents($file) : FALSE;

      return is_string($content) ? $this->normalize($content) : NULL;
    }
    finally {
      if (is_file($file)) {
        unlink($file);
      }
    }
  }

  /**
   * Condition the saved buffer into something the field can hold.
   *
   * Drops a single trailing newline the editor appended by convention, and
   * filters the control bytes a file written by another program can carry -
   * what came back is text somebody typed elsewhere, not text this library
   * composed.
   *
   * @param string $content
   *   The raw saved buffer.
   *
   * @return string
   *   The buffer without one trailing newline (CRLF or LF).
   */
  protected function normalize(string $content): string {
    return Ansi::sanitize((string) preg_replace('/\r?\n\z/', '', $content));
  }

  /**
   * Write the initial buffer to the exchange file.
   *
   * @param string $file
   *   The temp file path.
   * @param string $initial
   *   The buffer to seed.
   *
   * @return bool
   *   TRUE when the write succeeded.
   */
  protected function seed(string $file, string $initial): bool {
    return file_put_contents($file, $initial) !== FALSE;
  }

  /**
   * Create a temporary file to exchange the buffer with the editor.
   *
   * @return string|null
   *   The path, or NULL when a temp file could not be created.
   */
  protected function tempFile(): ?string {
    $file = tempnam(sys_get_temp_dir(), 'tui-editor-');

    return $file === FALSE ? NULL : $file;
  }

  /**
   * Run the editor against a file, inheriting the controlling terminal.
   *
   * @param string $command
   *   The editor command (may include arguments).
   * @param string $file
   *   The file to edit.
   *
   * @return int
   *   The editor's exit code (non-zero signals an aborted edit).
   */
  protected function spawn(string $command, string $file): int {
    // @codeCoverageIgnoreStart
    $process = proc_open($command . ' ' . escapeshellarg($file), [STDIN, STDOUT, STDERR], $pipes);

    if (!is_resource($process)) {
      return 1;
    }

    return proc_close($process);
    // @codeCoverageIgnoreEnd
  }

}
