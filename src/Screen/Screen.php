<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * The root: one layout, and whether the frame takes the whole terminal.
 *
 * Occupying the terminal sits here rather than on a layout because it is a fact
 * about the terminal, not about any arrangement inside it - which is what makes
 * a layout portable between a frame that stretches and one that shrinks.
 *
 * @package DrevOps\Tui\Screen
 */
final class Screen {

  /**
   * The layout arranging this screen, once one is given.
   */
  protected ?AbstractLayout $layout = NULL;

  /**
   * Whether the frame takes the whole terminal.
   */
  protected bool $fullscreen = FALSE;

  /**
   * Arrange this screen with a layout.
   *
   * @param \DrevOps\Tui\Screen\AbstractLayout $layout
   *   The layout.
   *
   * @return $this
   *   The screen.
   */
  public function layout(AbstractLayout $layout): self {
    $this->layout = $layout;

    return $this;
  }

  /**
   * The layout arranging this screen.
   *
   * @return \DrevOps\Tui\Screen\AbstractLayout
   *   The layout.
   */
  public function currentLayout(): AbstractLayout {
    if (!$this->layout instanceof AbstractLayout) {
      throw new \LogicException('This screen has no layout, so it has no regions to place a block in.');
    }

    return $this->layout;
  }

  /**
   * Take the whole terminal rather than fitting the contents.
   *
   * @return $this
   *   The screen.
   */
  public function fullscreen(): self {
    $this->fullscreen = TRUE;

    return $this;
  }

  /**
   * Whether the frame takes the whole terminal.
   *
   * @return bool
   *   TRUE when it does.
   */
  public function isFullscreen(): bool {
    return $this->fullscreen;
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

}
