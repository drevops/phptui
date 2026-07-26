<?php

declare(strict_types=1);

namespace DrevOps\Tui\Primitive;

use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A progress primitive for slow work: a spinner, or a determinate bar.
 *
 * Wraps a callback and shows it running on a single rewritten line, passing
 * the callback's return value straight back. Whether the length is known
 * decides the look: with no total the line is an animated spinner; with a
 * total it is a bar that fills as it advances. Either way the theme draws the
 * glyphs and accent, so it matches the active theme and honours its colour and
 * Unicode modes. The callback receives the primitive and drives it with
 * `advance()`.
 *
 * On an interactive terminal it hides the cursor, redraws the line in place on
 * each advance, and restores the cursor when the callback returns - even if it
 * throws. Off a TTY it emits no control sequences: it prints the caption once
 * as a plain line and every advance renders nothing.
 *
 * @package DrevOps\Tui\Primitive
 */
final class Progress {

  /**
   * The total number of steps; NULL for an indeterminate spinner.
   */
  protected ?int $total;

  /**
   * The number of completed steps.
   */
  protected int $current = 0;

  /**
   * The spinner animation frame counter.
   */
  protected int $frame = 0;

  /**
   * The trailing label shown after a determinate bar.
   */
  protected string $label = '';

  /**
   * Construct a progress primitive.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal the line is drawn on.
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme that draws the spinner glyphs and the bar.
   * @param bool $active
   *   Whether to draw control sequences (TRUE) or stay plain (FALSE).
   * @param int|null $total
   *   The number of steps for a determinate bar, or NULL for an indeterminate
   *   spinner. A negative total clamps to zero.
   * @param string $caption
   *   The caption shown beside the indicator.
   */
  public function __construct(
    protected Terminal $terminal,
    protected ThemeInterface $theme,
    protected bool $active,
    ?int $total,
    protected string $caption,
  ) {
    $this->total = $total === NULL ? NULL : max(0, $total);
  }

  /**
   * Run the callback, showing progress while it works.
   *
   * @param callable(self): TReturn $work
   *   The work to run; it receives this primitive so it can drive the updates.
   *
   * @return TReturn
   *   The callback's return value.
   *
   * @template TReturn
   */
  public function run(callable $work): mixed {
    if (!$this->active) {
      // Off a TTY the indicator is invisible chrome, so a single plain caption
      // line is the whole trace. Flush it so a block-buffered stream shows the
      // caption before the work runs, not after.
      $this->terminal->write($this->caption . "\n");
      $this->terminal->flush();

      return $work($this);
    }

    $this->terminal->write(TerminalControl::hideCursor());
    $this->draw();

    try {
      return $work($this);
    }
    finally {
      // A throw in the work must still leave a clean line and a visible cursor.
      $this->finish();
      $this->terminal->write(TerminalControl::showCursor());
      $this->terminal->flush();
    }
  }

  /**
   * Advance the indicator by one step, optionally replacing the label.
   *
   * For a spinner this ticks the animation one frame; for a bar it fills one
   * step of the total and updates the trailing label.
   *
   * @param string|null $label
   *   The new label, or NULL to keep the current one.
   */
  public function advance(?string $label = NULL): void {
    if ($label !== NULL) {
      $this->label = $label;
    }

    if ($this->total === NULL) {
      $this->frame++;
    }
    else {
      $this->current = min($this->current + 1, $this->total);
    }

    if ($this->active) {
      $this->draw();
    }
  }

  /**
   * Redraw the line in place with the current frame.
   */
  protected function draw(): void {
    // Return to column zero, paint the frame, then erase any tail a shorter
    // frame would leave behind.
    $this->terminal->write("\r" . $this->frameContent() . TerminalControl::eraseToLineEnd());
    $this->terminal->flush();
  }

  /**
   * Settle the line once the callback returns.
   */
  protected function finish(): void {
    if ($this->total === NULL) {
      // The spinner is transient: wipe its line.
      $this->terminal->write("\r" . TerminalControl::eraseLine());

      return;
    }

    // The bar settles at its final state on its own line.
    $this->draw();
    $this->terminal->write("\n");
  }

  /**
   * The current frame: a themed spinner line or a themed bar line.
   *
   * @return string
   *   The rendered line.
   */
  protected function frameContent(): string {
    return $this->total === NULL
      ? $this->theme->renderSpinner($this->frame, $this->caption)
      : $this->theme->renderProgressBar($this->current, $this->total, $this->caption, $this->label);
  }

}
