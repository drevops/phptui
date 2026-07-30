<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\ActivateCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Element\ProgressElementsInterface;
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
   * @var \Closure(self): void|null
   */
  protected ?\Closure $work = NULL;

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
   * Set the work this block runs.
   *
   * @param \Closure $work
   *   An `fn(\DrevOps\Tui\Block\Progress $progress): void` doing the work and
   *   advancing the block as it goes.
   *
   * @return static
   *   The block.
   */
  public function work(\Closure $work): static {
    $this->work = $work;

    return $this;
  }

  /**
   * Report progress.
   *
   * @param int $steps
   *   The steps done since the last report.
   *
   * @return static
   *   The block.
   */
  public function advance(int $steps = 1): static {
    // Work can report a step backwards, and a spinner frame is an index: both
    // are clamped here so no caller can drive the block into a state it could
    // not draw.
    $this->current = max(0, $this->current + $steps);
    $this->frame = max(0, $this->frame + $steps);

    if ($this->total !== NULL) {
      $this->current = min($this->current, $this->total);
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

    ($this->work)($this);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, ProgressElementsInterface::class, 'progress');
    $caption = $elements->progressCaption($this->caption);

    if ($this->total === NULL) {
      return $elements->progressSpinner($this->frame) . ' ' . $caption;
    }

    $filled = (int) round($this->current / $this->total * self::TRACK_WIDTH);

    return $caption . ' ' . $elements->progressTrack($filled, self::TRACK_WIDTH) . ' ' . $elements->progressCount($this->current, $this->total);
  }

}
