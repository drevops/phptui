<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\ProgressElements;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Work that runs when activated, with an indicator while it does.
 *
 * It is the block that separates taking the cursor from reaching the result: it
 * focuses and it acts, and nothing it does becomes an answer.
 *
 * @package DrevOps\Tui\Block
 */
final class Progress implements BlockInterface {

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
   * @return $this
   *   The block.
   */
  public function steps(int $total): self {
    if ($total < 1) {
      throw new \InvalidArgumentException('Work with no steps cannot report progress; leave the total unset for a spinner.');
    }

    $this->total = $total;

    return $this;
  }

  /**
   * Report progress.
   *
   * @param int $steps
   *   The steps done since the last report.
   *
   * @return $this
   *   The block.
   */
  public function advance(int $steps = 1): self {
    $this->current += $steps;
    $this->frame += $steps;

    if ($this->total !== NULL) {
      $this->current = min($this->current, $this->total);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    if (!$theme instanceof ProgressElements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw progress: it does not implement %s.', $theme::class, ProgressElements::class));
    }

    $caption = $theme->progressCaption($this->caption);

    if ($this->total === NULL) {
      return $theme->progressSpinner($this->frame) . ' ' . $caption;
    }

    $filled = (int) round($this->current / $this->total * self::TRACK_WIDTH);

    return $caption . ' ' . $theme->progressTrack($filled, self::TRACK_WIDTH) . ' ' . $theme->progressCount($this->current, $this->total);
  }

}
