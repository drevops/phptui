<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Screen\Capability\BorderCapableInterface;
use DrevOps\Tui\Screen\Capability\BorderCapableTrait;
use DrevOps\Tui\Screen\Capability\ScrollCapableInterface;
use DrevOps\Tui\Screen\Capability\ScrollCapableTrait;

/**
 * A named container inside a layout.
 *
 * A block addresses a region by name, so nothing depends on the order things
 * were declared in.
 *
 * The blocks run along one axis and are packed from either end of it, which
 * is placement rather than sizing: a block takes the space it drew whichever
 * end it was packed from.
 *
 * A region declares a size without computing one: the layout is the only
 * thing that sees every sibling, so the arithmetic belongs to it. A
 * content-sized region's extent is counted where blocks are drawn, and the
 * layout is handed the number.
 *
 * @package DrevOps\Tui\Screen
 */
final class Region implements BorderCapableInterface, ScrollCapableInterface {

  use BorderCapableTrait;
  use ScrollCapableTrait;

  /**
   * How this region's share of the axis is determined.
   */
  protected Sizing $sizing = Sizing::Flex;

  /**
   * The cell count or flex share; 0 when the region is content-sized.
   */
  protected int $size = 1;

  /**
   * The direction the blocks inside it run.
   */
  protected Axis $flow = Axis::Rows;

  /**
   * Whether a panel in it draws its preview rather than its row.
   */
  protected bool $previews = FALSE;

  /**
   * The blocks packed from the start of its flow, in the order they were added.
   *
   * @var list<\DrevOps\Tui\Block\BlockInterface>
   */
  protected array $head = [];

  /**
   * The blocks packed from the end of its flow, in the order they were added.
   *
   * @var list<\DrevOps\Tui\Block\BlockInterface>
   */
  protected array $tail = [];

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
   * @param int $cells
   *   The cells to take.
   *
   * @return $this
   *   The region.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the cell count is 0 or negative.
   */
  public function fixed(int $cells): self {
    if ($cells < 1) {
      throw new FormException('A fixed size is a count of cells, so it cannot be 0.');
    }

    $this->sizing = Sizing::Fixed;
    $this->size = $cells;

    return $this;
  }

  /**
   * Take a share of the cells the fixed regions leave.
   *
   * Shares do not sum to anything in particular, so 30, 40, 30 and 3, 4, 3
   * mean the same thing.
   *
   * @param int $share
   *   The share to take.
   *
   * @return $this
   *   The region.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the share is 0 or negative.
   */
  public function flex(int $share): self {
    if ($share < 1) {
      throw new FormException('A flex share divides the remainder, so it cannot be 0.');
    }

    $this->sizing = Sizing::Flex;
    $this->size = $share;

    return $this;
  }

  /**
   * Take as much of the axis as this region's contents come to.
   *
   * The extent is counted where blocks are drawn and handed to the layout, so
   * this stays a declaration, like the other two, rather than a measurement.
   *
   * @return $this
   *   The region.
   */
  public function content(): self {
    $this->sizing = Sizing::Content;
    $this->size = 0;

    return $this;
  }

  /**
   * How this region's share of the axis is determined.
   *
   * @return \DrevOps\Tui\Screen\Sizing
   *   The kind.
   */
  public function sizing(): Sizing {
    return $this->sizing;
  }

  /**
   * The cells this region declared, or the share it takes.
   *
   * @return int
   *   The number, or 0 when the region is content-sized.
   */
  public function size(): int {
    return $this->size;
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
   * Show a panel in this region as a window onto it rather than as a row.
   *
   * A row is one line and a window has depth, so which of the two a panel
   * draws is a question about the space rather than about the panel. The
   * space is known only to the arrangement, so the choice is declared here
   * and not on the block.
   *
   * @return $this
   *   The region.
   */
  public function previews(): self {
    $this->previews = TRUE;

    return $this;
  }

  /**
   * Whether a panel in this region draws its preview rather than its row.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function isPreviewing(): bool {
    return $this->previews;
  }

  /**
   * Draw a block in this region.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The region.
   */
  public function add(BlockInterface $block): self {
    $this->head[] = $block;

    return $this;
  }

  /**
   * Draw a block before everything already in this region.
   *
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   *
   * @return $this
   *   The region.
   */
  public function prepend(BlockInterface $block): self {
    array_unshift($this->head, $block);

    return $this;
  }

  /**
   * Draw a block at the far end of this region's flow.
   *
   * Where {@see self::add()} packs from the start of the axis the blocks run
   * along, this packs from the end of it.
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
    return [...$this->head, ...$this->tail];
  }

  /**
   * The blocks packed from the start of this region's flow.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks.
   */
  public function headBlocks(): array {
    return $this->head;
  }

  /**
   * The blocks packed from the end of this region's flow.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks; empty when none were packed from the end.
   */
  public function tailBlocks(): array {
    return $this->tail;
  }

  /**
   * {@inheritdoc}
   */
  protected function surface(): string {
    return sprintf('Region "%s"', $this->name);
  }

}
