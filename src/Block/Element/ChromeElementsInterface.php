<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements no single block owns.
 *
 * The one elements interface named for something other than a block, because
 * what it draws belongs to none of them. The frame surrounds every region at
 * once and the overflow marker says a region's contents outran it: neither is
 * anything a block could ask for, since a block only ever fills the space it is
 * given and never learns where that space ends, so both are the renderer's to
 * draw. The gutter is the other kind of shared piece - every block that can
 * come and go with the answers steps in behind the same one, so a chain of a
 * row, a note and a row steps in together rather than one kind at a time.
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
   * The mark saying a region's contents run past the space it was given.
   *
   * The region's alone. A field that windows a long list to a page draws its
   * own, so the two marks are free to read differently on a theme that wants
   * them to.
   *
   * @param bool $above
   *   Whether the content it points at is above rather than below.
   *
   * @return string
   *   The styled mark.
   */
  public function chromeOverflowMarker(bool $above): string;

  /**
   * The blank gutter a block behind a condition is laid out after.
   *
   * A block there only once an earlier question is answered steps in from the
   * questions that decide it, so a panel reads as a hierarchy rather than as a
   * flat list. Every row that block contributes is laid out after this same
   * gutter, and a theme that would rather draw a flat list answers with none.
   *
   * @param int $depth
   *   How many answers had to be given before the block is there at all; zero
   *   for one that is always there.
   *
   * @return string
   *   The gutter, empty when nothing steps in.
   */
  public function chromeIndent(int $depth): string;

}
