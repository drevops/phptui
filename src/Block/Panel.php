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
   */
  public function modal(): static {
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
   * The panels you can descend into from this one.
   *
   * @return list<\DrevOps\Tui\Block\Panel>
   *   The sub-panels, in the order they were placed.
   */
  public function children(): array {
    if (!$this->layout instanceof LayoutInterface) {
      return [];
    }

    $children = [];

    foreach ($this->layout->names() as $name) {
      foreach ($this->layout->in($name)->blocks() as $block) {
        if ($block instanceof self) {
          $children[] = $block;
        }
      }
    }

    return $children;
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

}
