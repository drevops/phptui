<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Terminal;

/**
 * One physical line of parsed markup: its spans and whether it is a bullet.
 *
 * @package DrevOps\PhpTui\Terminal
 */
final readonly class MarkupLine {

  /**
   * Construct a line.
   *
   * @param bool $bullet
   *   Whether the line is an unordered-list item, drawn with a bullet marker.
   * @param list<\DrevOps\PhpTui\Terminal\MarkupSegment> $segments
   *   The inline spans, in order.
   */
  public function __construct(
    public bool $bullet,
    public array $segments,
  ) {
  }

}
