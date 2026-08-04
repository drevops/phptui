<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;

/**
 * A named container inside a layout.
 *
 * A region is a name, some blocks, and whether you can scroll them. Its name is
 * how a block says where it goes, so nothing depends on the order things were
 * declared in.
 *
 * The blocks run along one axis and are packed from either end of it, which is
 * placement rather than sizing: a block takes the space it drew whichever end
 * it was packed from.
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
   * The blocks packed from the start of its flow, in the order they were added.
   *
   * @var list<\DrevOps\Tui\Block\BlockInterface>
   */
  protected array $blocks = [];

  /**
   * The blocks packed from the end of its flow, in the order they were added.
   *
   * @var list<\DrevOps\Tui\Block\BlockInterface>
   */
  protected array $tail = [];

  /**
   * The first row of its contents that is visible.
   */
  protected int $offset = 0;

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

  /**
   * Draw a block in this region.
   *
   * A region never knows which kind it was given, which is why a breadcrumb can
   * go wherever a field can.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The region.
   */
  public function add(BlockInterface $block): self {
    $this->blocks[] = $block;

    return $this;
  }

  /**
   * Draw a block before everything already in this region.
   *
   * What a region holds is normally in the order it was declared in, so this is
   * for the standing text a driver puts above rows that were placed before it
   * knew there would be any.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The region.
   */
  public function prepend(BlockInterface $block): self {
    array_unshift($this->blocks, $block);

    return $this;
  }

  /**
   * Draw a block at the far end of this region's flow.
   *
   * Where {@see self::add()} packs from the start of the axis the blocks run
   * along, this packs from the end of it - so a footer flowing across keeps its
   * key hints at the left and a version string at the right, and one flowing
   * down keeps a standing note on its last row.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The region.
   */
  public function tail(BlockInterface $block): self {
    $this->tail[] = $block;

    return $this;
  }

  /**
   * The blocks drawn in this region, in the order they are drawn.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks packed from the start, then the ones packed from the end.
   */
  public function blocks(): array {
    return [...$this->blocks, ...$this->tail];
  }

  /**
   * The blocks packed from the start of this region's flow.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks.
   */
  public function headBlocks(): array {
    return $this->blocks;
  }

  /**
   * The blocks packed from the end of this region's flow.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks, none when everything it holds runs from the start.
   */
  public function tailBlocks(): array {
    return $this->tail;
  }

  /**
   * Move the window onto this region's contents.
   *
   * @param int $row
   *   The first row of the contents to show.
   *
   * @return $this
   *   The region.
   */
  public function scrollTo(int $row): self {
    if (!$this->scrolls) {
      throw new \LogicException(sprintf('Region "%s" does not scroll, so it cannot be scrolled to row %d.', $this->name, $row));
    }

    $this->offset = max(0, $row);

    return $this;
  }

  /**
   * The first row of this region's contents that is visible.
   *
   * @param int $content
   *   The rows its contents come to.
   * @param int $visible
   *   The rows it was given.
   *
   * @return int
   *   The offset, never far enough to scroll the contents off their own end.
   */
  public function offset(int $content, int $visible): int {
    return min($this->offset, max(0, $content - $visible));
  }

}
