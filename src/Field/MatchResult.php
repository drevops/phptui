<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

/**
 * The outcome of matching a query against a candidate label.
 *
 * Carries the relevance score used to rank a candidate against its peers and
 * the label character indices the query matched, used to highlight them.
 *
 * @package DrevOps\Tui\Field
 */
final readonly class MatchResult {

  /**
   * Construct a match result.
   *
   * @param int $score
   *   The relevance score: higher ranks ahead. Tiered so an exact match always
   *   outranks a prefix, a prefix a substring, and a substring a looser
   *   subsequence, whatever the finer refinement within a tier.
   * @param list<int> $positions
   *   The zero-based indices of the matched characters in the candidate, in
   *   ascending order, for highlighting.
   */
  public function __construct(
    public int $score,
    public array $positions,
  ) {
  }

}
