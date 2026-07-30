<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the window chrome composes.
 *
 * The one elements interface named for something other than a block, because
 * what it draws belongs to no block: the frame surrounds every region at once
 * and the overflow marker says a region's contents outran it. Neither is
 * anything a block could ask for - a block only ever fills the space it is
 * given and never learns where that space ends - so both are the renderer's to
 * draw, and this is where it reaches for them.
 *
 * The prefix follows the same rule the block interfaces do: every element is
 * named for its owner, so a theme implements all of them on one class without
 * collisions.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface ChromeElementsInterface {

  /**
   * Style the frame drawn around everything.
   *
   * @param string $text
   *   The run of box-drawing characters.
   *
   * @return string
   *   The styled frame.
   */
  public function chromeBorder(string $text): string;

  /**
   * The mark pointing at content past an edge of the frame.
   *
   * @param bool $above
   *   Whether the content it points at is above rather than below.
   *
   * @return string
   *   The styled mark.
   */
  public function chromeOverflowMarker(bool $above): string;

}
