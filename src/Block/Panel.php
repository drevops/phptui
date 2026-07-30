<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\PanelElements;
use DrevOps\Tui\Screen\AbstractLayout;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * A destination you can go into and come back from.
 *
 * It is the only block that nests a layout, and the only one you navigate into:
 * entering it replaces the screen and grows the trail, leaving it restores both.
 * A region can hold blocks and a layout can arrange them, but neither is
 * somewhere you go.
 *
 * Nested inside another panel it draws a row you select. Once entered it draws
 * nothing of its own, because its blocks do.
 *
 * @package DrevOps\Tui\Block
 */
final class Panel implements BlockInterface {

  /**
   * The layout arranging this panel's blocks, once one is given.
   */
  protected ?AbstractLayout $layout = NULL;

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
   * @param \DrevOps\Tui\Screen\AbstractLayout $layout
   *   The layout.
   *
   * @return $this
   *   The panel.
   */
  public function layout(AbstractLayout $layout): self {
    $this->layout = $layout;

    return $this;
  }

  /**
   * The layout arranging this panel's blocks.
   *
   * @return \DrevOps\Tui\Screen\AbstractLayout
   *   The layout.
   */
  public function currentLayout(): AbstractLayout {
    if (!$this->layout instanceof AbstractLayout) {
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
   * Mark this as the panel you are currently in.
   *
   * @return $this
   *   The panel.
   */
  public function enter(): self {
    $this->entered = TRUE;

    return $this;
  }

  /**
   * Leave this panel, so it draws as a row again.
   *
   * @return $this
   *   The panel.
   */
  public function leave(): self {
    $this->entered = FALSE;

    return $this;
  }

  /**
   * Whether this is the panel you are currently in.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isEntered(): bool {
    return $this->entered;
  }

  /**
   * Draw over what is behind rather than replacing it.
   *
   * @return $this
   *   The panel.
   */
  public function modal(): self {
    $this->modal = TRUE;

    return $this;
  }

  /**
   * Whether this panel draws over what is behind it.
   *
   * @return bool
   *   TRUE when it does.
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
    if (!$this->layout instanceof AbstractLayout) {
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

    if (!$theme instanceof PanelElements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw a panel: it does not implement %s.', $theme::class, PanelElements::class));
    }

    return $theme->panelTitle($this->title);
  }

}
