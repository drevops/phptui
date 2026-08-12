<?php

declare(strict_types=1);

namespace DrevOps\Tui\Terminal;

/**
 * One inline span of parsed markup: its kind, visible text and link target.
 *
 * @package DrevOps\Tui\Terminal
 */
final readonly class MarkupSegment {

  /**
   * Construct a segment.
   *
   * @param \DrevOps\Tui\Terminal\MarkupType $kind
   *   The span kind.
   * @param string $text
   *   The visible text (the inner text of a styled span, or a link's label).
   * @param string $url
   *   The link target, for a Link span; empty otherwise.
   */
  public function __construct(
    public MarkupType $kind,
    public string $text,
    public string $url = '',
  ) {
  }

}
