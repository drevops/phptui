<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\ActivateCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Element\ProgressElementsInterface;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Work that runs when activated, with an indicator while it does.
 *
 * It is the block that separates taking the cursor from reaching the result: it
 * focuses and it acts, and nothing it does becomes an answer.
 *
 * @package DrevOps\Tui\Block
 */
final class Progress extends AbstractBlock implements ActivateCapableInterface, DependCapableInterface, FocusCapableInterface {

  use DependCapableTrait;
  use FocusCapableTrait;

  /**
   * The cells a bar's run takes.
   */
  protected const int TRACK_WIDTH = 10;

  /**
   * The steps there are, or NULL when the length is unknown.
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
   * What the work says it is doing, shown after the indicator.
   */
  protected string $label = '';

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
    protected string $caption,
  ) {
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
   * Say how many steps the work has, which is what earns it a bar.
   *
   * @param int $total
   *   The steps.
   *
   * @return static
   *   The block.
   */
  public function steps(int $total): static {
    if ($total < 1) {
      throw new \InvalidArgumentException('Work with no steps cannot report progress; leave the total unset for a spinner.');
    }

    $this->total = $total;

    return $this;
  }

  /**
   * The steps the work has, which is what earns it a bar.
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
   * Say what the work is doing right now.
   *
   * @param string $label
   *   The label, shown after the bar or the spinner.
   *
   * @return static
   *   The block.
   */
  public function label(string $label): static {
    $this->label = $label;

    return $this;
  }

  /**
   * What the work says it is doing.
   *
   * @return string
   *   The label, empty when it says nothing.
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
   *   What is being done now, or NULL to leave the label as it stands.
   *
   * @return static
   *   The block.
   */
  public function advance(int $steps = 1, ?string $label = NULL): static {
    // Work can report a step backwards, and a spinner frame is an index: both
    // are clamped here so no caller can drive the block into a state it could
    // not draw.
    $this->current = max(0, $this->current + $steps);
    $this->frame = max(0, $this->frame + $steps);

    if ($this->total !== NULL) {
      $this->current = min($this->current, $this->total);
    }

    if ($label !== NULL) {
      $this->label = $label;
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

    // The work is handed a reporter rather than the block, so it says only that
    // one more step is done and never reaches the state behind the indicator.
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
    $caption = $elements->progressCaption($this->caption);
    // The caption names the work and stays put; the label is what that work is
    // doing right now, so it trails the indicator and changes under it.
    $label = $this->label === '' ? '' : ' ' . $elements->progressCaption($this->label);

    if ($this->total === NULL) {
      return $elements->progressSpinner($this->frame) . ' ' . $caption . $label;
    }

    $filled = (int) round($this->current / $this->total * self::TRACK_WIDTH);

    return $caption . ' ' . $elements->progressTrack($filled, self::TRACK_WIDTH) . ' ' . $elements->progressCount($this->current, $this->total) . $label;
  }

}
