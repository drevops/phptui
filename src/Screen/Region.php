<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * A named container inside a layout.
 *
 * A region is a name, some blocks, and whether you can scroll them. Its name is
 * how a block says where it goes, so nothing depends on the order things were
 * declared in.
 *
 * It declares a size without computing one: the arithmetic belongs to the
 * layout, which is the only thing that sees every sibling.
 *
 * @package DrevOps\Tui\Screen
 */
final class Region {

  /**
   * The cells this region takes, or NULL when it takes a share instead.
   */
  protected ?int $fixed = NULL;

  /**
   * The share of the remainder this region takes, or NULL when it is fixed.
   */
  protected ?int $flex = 1;

  /**
   * The direction the blocks inside it run.
   */
  protected Axis $flow = Axis::Rows;

  /**
   * Whether its contents may outrun it.
   */
  protected bool $scrolls = FALSE;

  /**
   * Construct a region.
   *
   * @param string $name
   *   The name a block addresses it by.
   */
  public function __construct(
    protected string $name,
  ) {
  }

  /**
   * The name a block addresses this region by.
   *
   * @return string
   *   The name.
   */
  public function name(): string {
    return $this->name;
  }

  /**
   * Take a fixed number of cells of the axis.
   *
   * A header is one line whatever the terminal height, and no proportion can
   * say that, which is what this is for.
   *
   * @param int $cells
   *   The cells to take.
   *
   * @return $this
   *   The region.
   */
  public function fixed(int $cells): self {
    if ($cells < 1) {
      throw new \InvalidArgumentException('A fixed size is a count of cells, so it cannot be 0.');
    }

    $this->fixed = $cells;
    $this->flex = NULL;

    return $this;
  }

  /**
   * Take a share of whatever the fixed regions leave behind.
   *
   * Shares do not sum to anything in particular, so 30, 40, 30 and 3, 4, 3 mean
   * the same thing.
   *
   * @param int $share
   *   The share to take.
   *
   * @return $this
   *   The region.
   */
  public function flex(int $share): self {
    if ($share < 1) {
      throw new \InvalidArgumentException('A flex share divides the remainder, so it cannot be 0.');
    }

    $this->flex = $share;
    $this->fixed = NULL;

    return $this;
  }

  /**
   * The cells this region was declared to take.
   *
   * @return int|null
   *   The cells, or NULL when it takes a share instead.
   */
  public function fixedSize(): ?int {
    return $this->fixed;
  }

  /**
   * The share of the remainder this region was declared to take.
   *
   * @return int|null
   *   The share, or NULL when it takes a fixed size instead.
   */
  public function flexShare(): ?int {
    return $this->flex;
  }

  /**
   * Run the blocks inside this region along an axis.
   *
   * @param \DrevOps\Tui\Screen\Axis $axis
   *   The direction they run.
   *
   * @return $this
   *   The region.
   */
  public function flow(Axis $axis): self {
    $this->flow = $axis;

    return $this;
  }

  /**
   * The direction the blocks inside this region run.
   *
   * @return \DrevOps\Tui\Screen\Axis
   *   The direction.
   */
  public function flowAxis(): Axis {
    return $this->flow;
  }

  /**
   * Let this region's contents outrun it.
   *
   * @return $this
   *   The region.
   */
  public function scrolls(): self {
    $this->scrolls = TRUE;

    return $this;
  }

  /**
   * Whether this region's contents may outrun it.
   *
   * @return bool
   *   TRUE when they may.
   */
  public function isScrolling(): bool {
    return $this->scrolls;
  }

}
