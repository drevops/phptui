<?php

declare(strict_types=1);

namespace DrevOps\Tui\Primitive;

/**
 * The handle a progress widget's work drives to advance its indicator.
 *
 * Each `advance()` reports one step: the panel repaints the row so a
 * determinate bar fills or a spinner ticks. The reporter holds no state of its
 * own - it forwards to the supplied callback, which owns the field's live count
 * and the repaint.
 *
 * @package DrevOps\Tui\Primitive
 */
final class ProgressReporter {

  /**
   * Construct a reporter.
   *
   * @param \Closure $onAdvance
   *   An `fn(?string $label): void` moving the indicator one step, storing the
   *   label when given, and repainting the panel.
   */
  public function __construct(protected \Closure $onAdvance) {
  }

  /**
   * Advance the indicator by one step, optionally replacing its label.
   *
   * @param string|null $label
   *   The trailing label to show, or NULL to keep the current one.
   */
  public function advance(?string $label = NULL): void {
    ($this->onAdvance)($label);
  }

}
