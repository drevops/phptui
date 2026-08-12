<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\ActivateCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Element\ProgressElementsInterface;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Work that runs when activated, with an indicator while it does.
 *
 * The block takes focus and acts, and nothing it does becomes an answer.
 *
 * @package DrevOps\Tui\Block
 */
final class Progress extends AbstractBlock implements ActivateCapableInterface, DependCapableInterface, FocusCapableInterface {

  use DependCapableTrait;
  use FocusCapableTrait;

  /**
   * The width of the bar's track, in cells.
   */
  protected const int TRACK_WIDTH = 10;

  /**
   * The total number of steps, or NULL when the length is unknown.
   */
  protected ?int $total = NULL;

  /**
   * The steps done.
   */
  protected int $current = 0;

  /**
   * The spinner frame, when the length is unknown.
   */
  protected int $frame = 0;

  /**
   * The work activating this block runs, or NULL when it has none.
   *
   * @var \Closure(\DrevOps\Tui\Primitive\ProgressReporter): void|null
   */
  protected ?\Closure $work = NULL;

  /**
   * The text describing what the work is doing, shown after the indicator.
   */
  protected string $label = '';

  /**
   * The caption naming the work.
   */
  protected string $caption;

  /**
   * Construct a progress block.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $caption
   *   The caption naming the work.
   */
  public function __construct(
    protected string $id,
    string $caption,
  ) {
    $this->caption = Ansi::sanitize($caption);
  }

  /**
   * The id this block is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * The caption naming the work.
   *
   * @return string
   *   The caption.
   */
  public function caption(): string {
    return $this->caption;
  }

  /**
   * Declare how many steps the work has; a known total draws a bar.
   *
   * @param int $total
   *   The steps.
   *
   * @return static
   *   The block.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the declared step count is below one.
   */
  public function steps(int $total): static {
    if ($total < 1) {
      throw new FormException(sprintf('Progress row "%s" declares %d steps; a determinate bar needs at least one step (omit steps() for a spinner).', $this->id, $total));
    }

    $this->total = $total;

    return $this;
  }

  /**
   * The number of steps the work has; a known total draws a bar.
   *
   * @return int|null
   *   The steps, or NULL when the length is unknown.
   */
  public function total(): ?int {
    return $this->total;
  }

  /**
   * The steps done.
   *
   * @return int
   *   The count.
   */
  public function current(): int {
    return $this->current;
  }

  /**
   * Set the text describing what the work is doing.
   *
   * @param string $label
   *   The label, shown after the bar or the spinner.
   *
   * @return static
   *   The block.
   */
  public function label(string $label): static {
    $this->label = Ansi::sanitize($label);

    return $this;
  }

  /**
   * The text describing what the work is doing.
   *
   * @return string
   *   The label, empty when none is set.
   */
  public function labelText(): string {
    return $this->label;
  }

  /**
   * Set the work this block runs.
   *
   * @param \Closure $work
   *   An `fn(\DrevOps\Tui\Primitive\ProgressReporter $reporter): void` doing
   *   the work, calling `advance()` on the reporter once per step.
   *
   * @return static
   *   The block.
   */
  public function work(\Closure $work): static {
    $this->work = $work;

    return $this;
  }

  /**
   * The work activating this block runs.
   *
   * @return \Closure|null
   *   The work, or NULL when activating it does nothing.
   */
  public function workload(): ?\Closure {
    return $this->work;
  }

  /**
   * Report progress.
   *
   * @param int $steps
   *   The steps done since the last report.
   * @param string|null $label
   *   The new label, or NULL to leave the label unchanged.
   *
   * @return static
   *   The block.
   */
  public function advance(int $steps = 1, ?string $label = NULL): static {
    // A report may step backwards and the spinner frame is an index, so both
    // are clamped to keep the block in a drawable state.
    $this->current = max(0, $this->current + $steps);
    $this->frame = max(0, $this->frame + $steps);

    if ($this->total !== NULL) {
      $this->current = min($this->current, $this->total);
    }

    if ($label !== NULL) {
      $this->label($label);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(): bool {
    if (!$this->work instanceof \Closure) {
      return FALSE;
    }

    // The work receives a reporter rather than the block, so it can only
    // report a step and never reaches the state behind the indicator.
    ($this->work)(new ProgressReporter(function (?string $label): void {
      $this->advance(1, $label);
    }));

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, ProgressElementsInterface::class, 'progress');
    $gutter = $this->elements($theme, ChromeElementsInterface::class, 'a conditional row')->chromeIndent($this->depth);
    $caption = $elements->progressCaption($this->caption);
    // The caption names the work and is fixed; the label describes the
    // current activity, so it follows the indicator and changes with it.
    $label = $this->label === '' ? '' : ' ' . $elements->progressCaption($this->label);
    // The row takes focus and activation starts the work, so it draws a
    // selector mark the way every other row does.
    $mark = $elements->progressSelector($this->isFocused()) . ' ';

    if ($this->total === NULL) {
      return $this->stepped($mark . $elements->progressSpinner($this->frame) . ' ' . $caption . $label, $gutter);
    }

    $filled = (int) round($this->current / $this->total * self::TRACK_WIDTH);

    return $this->stepped($mark . $caption . ' ' . $elements->progressTrack($filled, self::TRACK_WIDTH) . ' ' . $elements->progressCount($this->current, $this->total) . $label, $gutter);
  }

}
