<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\BindCapableTrait;
use DrevOps\Tui\Block\Capability\DescendCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Capability\OverlayCapableInterface;
use DrevOps\Tui\Block\Element\PanelElementsInterface;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A destination you can go into and come back from.
 *
 * It is the only block that nests a layout, and the only one you navigate into:
 * entering it replaces the screen and grows the trail; leaving restores both.
 * A region can hold blocks and a layout can arrange them, but neither is
 * somewhere you go.
 *
 * Nested inside another panel it draws a row you select. Once entered it draws
 * nothing of its own, because its blocks do.
 *
 * @package DrevOps\Tui\Block
 */
final class Panel extends AbstractBlock implements BindCapableInterface, DescendCapableInterface, FocusCapableInterface, OverlayCapableInterface {

  use BindCapableTrait;
  use FocusCapableTrait;

  /**
   * The layout arranging this panel's blocks, once one is given.
   */
  protected ?LayoutInterface $layout = NULL;

  /**
   * Whether it draws over what is behind it rather than replacing it.
   */
  protected bool $modal = FALSE;

  /**
   * Whether this is the panel you are currently in.
   */
  protected bool $entered = FALSE;

  /**
   * The standing text under its title.
   */
  protected string $description = '';

  /**
   * The way out of it, once it draws over what is behind it.
   */
  protected Buttons $buttons;

  /**
   * What prepares it before it is first entered, until that has been done.
   */
  protected ?\Closure $preload = NULL;

  /**
   * How the panels nested in it sit side by side, one entry per visual row.
   *
   * @var list<int>
   */
  protected array $grid = [];

  /**
   * Construct a panel.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $title
   *   The title it carries into the trail.
   */
  public function __construct(
    protected string $id,
    protected string $title,
  ) {
    $this->buttons = new Buttons();
  }

  /**
   * The id this panel is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * The title this panel carries into the trail.
   *
   * @return string
   *   The title.
   */
  public function title(): string {
    return $this->title;
  }

  /**
   * Set the standing text under this panel's title.
   *
   * @param string $description
   *   The description.
   *
   * @return static
   *   The panel.
   */
  public function description(string $description): static {
    $this->description = $description;

    return $this;
  }

  /**
   * The standing text under this panel's title.
   *
   * @return string
   *   The description, empty when it carries none.
   */
  public function descriptionText(): string {
    return $this->description;
  }

  /**
   * Label the way out of this panel.
   *
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The pair that closes it.
   *
   * @return static
   *   The panel.
   *
   * @throws \InvalidArgumentException
   *   When the pair is hidden on a panel that draws over what is behind it,
   *   which would strand it with no way out.
   */
  public function buttons(Buttons $buttons): static {
    $this->assertWayOut($this->modal, $buttons);
    $this->buttons = $buttons;

    return $this;
  }

  /**
   * The way out of this panel.
   *
   * @return \DrevOps\Tui\Model\Buttons
   *   The pair that closes it.
   */
  public function currentButtons(): Buttons {
    return $this->buttons;
  }

  /**
   * Prepare this panel before it is first entered.
   *
   * @param \Closure $work
   *   An `fn (): void` doing the preparation, such as one fetch several of the
   *   panel's fields then read.
   *
   * @return static
   *   The panel.
   */
  public function preload(\Closure $work): static {
    $this->preload = $work;

    return $this;
  }

  /**
   * What prepares this panel before it is first entered.
   *
   * @return \Closure|null
   *   The preparation, or NULL when there is none left to do.
   */
  public function preparation(): ?\Closure {
    return $this->preload;
  }

  /**
   * Do what was to be done before this panel is first entered.
   *
   * @return bool
   *   Whether anything was done. Preparation happens once, so every call after
   *   the first answers FALSE.
   */
  public function prepare(): bool {
    if (!$this->preload instanceof \Closure) {
      return FALSE;
    }

    ($this->preload)();
    $this->preload = NULL;

    return TRUE;
  }

  /**
   * Arrange this panel's blocks with a layout.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return static
   *   The panel.
   */
  public function layout(LayoutInterface $layout): static {
    $this->layout = $layout;

    return $this;
  }

  /**
   * The layout arranging this panel's blocks.
   *
   * @return \DrevOps\Tui\Screen\Layout\LayoutInterface
   *   The layout.
   */
  public function currentLayout(): LayoutInterface {
    if (!$this->layout instanceof LayoutInterface) {
      throw new \LogicException(sprintf('Panel "%s" has no layout, so it has no regions to place a block in.', $this->id));
    }

    return $this->layout;
  }

  /**
   * The region of a given name, to place blocks in.
   *
   * @param string $name
   *   The region name.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  public function in(string $name): Region {
    return $this->currentLayout()->in($name);
  }

  /**
   * {@inheritdoc}
   */
  public function enter(): static {
    $this->entered = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function leave(): static {
    $this->entered = FALSE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEntered(): bool {
    return $this->entered;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   *   When its buttons are hidden, which would leave it drawn over everything
   *   with no way out.
   */
  public function modal(): static {
    $this->assertWayOut(TRUE, $this->buttons);
    $this->modal = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isModal(): bool {
    return $this->modal;
  }

  /**
   * {@inheritdoc}
   *
   * A nested panel is a row you select rather than somewhere you are, so it
   * takes no key until you have gone into it - which is what leaves the keys
   * that move the cursor past it reaching the panel it sits in.
   */
  public function binds(Key $key): bool {
    return $this->entered && $this->boundAction($key) instanceof Action;
  }

  /**
   * {@inheritdoc}
   *
   * These are the keys you have while you are in a panel rather than inside
   * anything it holds, which is why moving the cursor, going into a nested
   * panel and coming back out again all resolve here.
   */
  public function hints(): array {
    return [
      new Hint('move', Action::MoveUp, Action::MoveDown),
      new Hint('select', Action::Activate),
      new Hint('go back', Action::Back),
    ];
  }

  /**
   * Sit the panels nested in this one side by side.
   *
   * @param int ...$rows
   *   One entry per visual row, naming how many nested panels share it, top to
   *   bottom; none leaves them one under another.
   *
   * @return static
   *   The panel.
   */
  public function grid(int ...$rows): static {
    $this->grid = array_values($rows);

    return $this;
  }

  /**
   * How the panels nested in this one sit side by side.
   *
   * @return list<int>
   *   The count of each visual row, empty when they run one under another.
   */
  public function gridRows(): array {
    return $this->grid;
  }

  /**
   * The fields this panel holds, in the order they were placed.
   *
   * Its own only: a nested panel is somewhere you go rather than something this
   * one holds, so what it asks belongs to it.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The fields.
   */
  public function fields(): array {
    $fields = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof Field) {
        $fields[] = $block;
      }
    }

    return $fields;
  }

  /**
   * The ids of the rows this panel holds, in the order they were placed.
   *
   * Every row that carries one, whether it collects an answer or only shows
   * something: an id that shows is still an id the form knows, which is what
   * tells a stray answer apart from one meant for a row that takes none.
   *
   * @return list<string>
   *   The ids.
   */
  public function ids(): array {
    $ids = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof Field || $block instanceof Markup || $block instanceof Progress) {
        $ids[] = $block->id();
      }
    }

    return $ids;
  }

  /**
   * The panels you can descend into from this one.
   *
   * @return list<\DrevOps\Tui\Block\Panel>
   *   The sub-panels, in the order they were placed.
   */
  public function children(): array {
    $children = [];

    foreach ($this->blocks() as $block) {
      if ($block instanceof self) {
        $children[] = $block;
      }
    }

    return $children;
  }

  /**
   * Everything placed in this panel, region by region, in placement order.
   *
   * @return list<\DrevOps\Tui\Block\BlockInterface>
   *   The blocks; empty while the panel has no layout to hold any.
   */
  public function blocks(): array {
    if (!$this->layout instanceof LayoutInterface) {
      return [];
    }

    $blocks = [];

    foreach ($this->layout->names() as $name) {
      foreach ($this->layout->in($name)->blocks() as $block) {
        $blocks[] = $block;
      }
    }

    return $blocks;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    // Show and Focus are a nested panel's, not an entered one's: once you are
    // in it, its blocks draw and it draws nothing of its own.
    if ($this->entered) {
      throw new \LogicException(sprintf('Panel "%s" is entered, so its blocks draw rather than the panel itself.', $this->id));
    }

    return $this->elements($theme, PanelElementsInterface::class, 'a panel')->panelTitle($this->title);
  }

  /**
   * {@inheritdoc}
   */
  protected function keyScope(): Scope {
    return Scope::navigation();
  }

  /**
   * Reject a panel that would draw over everything with no way out.
   *
   * @param bool $modal
   *   Whether it draws over what is behind it.
   * @param \DrevOps\Tui\Model\Buttons $buttons
   *   The pair that closes it.
   *
   * @throws \InvalidArgumentException
   *   When such a panel hides its buttons.
   */
  protected function assertWayOut(bool $modal, Buttons $buttons): void {
    if ($modal && !$buttons->show) {
      throw new \InvalidArgumentException(sprintf('Panel "%s" draws over what is behind it, so its buttons are its only way out and cannot be hidden.', $this->id));
    }
  }

}
