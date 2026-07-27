<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

/**
 * One physical line of parsed markup: its spans and whether it is a bullet.
 *
 * @package DrevOps\Tui\Render
 */
final readonly class MarkupLine {

  /**
   * Construct a line.
   *
   * @param bool $bullet
   *   Whether the line is an unordered-list item, drawn with a bullet marker.
   * @param list<\DrevOps\Tui\Render\MarkupSegment> $segments
   *   The inline spans, in order.
   */
  public function __construct(
    public bool $bullet,
    public array $segments,
  ) {
  }

}
