<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Screen\Capability\ScrollCapableInterface;
use DrevOps\Tui\Screen\Capability\ScrollCapableTrait;

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
 * layout, which is the only thing that sees every sibling. Even a region as
 * deep as what it holds only says so - what it holds is counted where blocks
 * are drawn, and the layout is handed the number.
 *
 * @package DrevOps\Tui\Screen
 */
final class Region implements ScrollCapableInterface {

  use ScrollCapableTrait;

  /**
   * How it asks for its share of the axis.
   */
  protected Sizing $sizing = Sizing::Flex;

  /**
   * The cells it asked for, or the share it takes; nothing when it is measured.
   */
  protected int $size = 1;

  /**
   * The direction the blocks inside it run.
   */
  protected Axis $flow = Axis::Rows;

  /**
   * Whether a panel in it shows what is behind it rather than a row.
   */
  protected bool $previews = FALSE;

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

    $this->sizing = Sizing::Fixed;
    $this->size = $cells;

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

    $this->sizing = Sizing::Flex;
    $this->size = $share;

    return $this;
  }

  /**
   * Take as much of the axis as what this region holds comes to.
   *
   * A window is as deep as the rows behind it, and neither a count of cells nor
   * a share of the remainder can say that. What it comes to is measured where
   * blocks are drawn and handed to the layout, so this stays a declaration like
   * the other two rather than a measurement.
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
   * How this region asks for its share of the axis.
   *
   * @return \DrevOps\Tui\Screen\Sizing
   *   The kind.
   */
  public function sizing(): Sizing {
    return $this->sizing;
  }

  /**
   * The cells this region asked for, or the share it takes.
   *
   * @return int
   *   The number, which is nothing at all when it is measured instead.
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
   * A row has one line to say what is behind a panel and a window has the depth
   * to show it, so which of the two a panel draws is a question about the space
   * rather than about the panel. Only the arrangement knows which the space is,
   * which is why it is declared here and not on the block.
   *
   * @return $this
   *   The region.
   */
  public function previews(): self {
    $this->previews = TRUE;

    return $this;
  }

  /**
   * Whether a panel in this region shows what is behind it rather than a row.
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
   * {@inheritdoc}
   */
  protected function surface(): string {
    return sprintf('Region "%s"', $this->name);
  }

}
