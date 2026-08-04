<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

/**
 * How closely a candidate matches a query, coarsest band first.
 *
 * A tighter tier always outranks a looser one regardless of the finer
 * within-tier refinement, so the tier's weight dominates the match score.
 *
 * @package DrevOps\Tui\Field
 */
enum MatchTier {

  // The candidate equals the query (case-insensitively).
  case Exact;

  // The query is a prefix of the candidate.
  case Prefix;

  // The query appears in the candidate as a contiguous substring.
  case Substring;

  // The query's characters appear in order but not contiguously.
  case Subsequence;

  /**
   * The ranking weight: a higher weight ranks ahead.
   *
   * @return int
   *   The weight, from 4 (exact) down to 1 (subsequence).
   */
  public function weight(): int {
    return match ($this) {
      self::Exact => 4,
      self::Prefix => 3,
      self::Substring => 2,
      self::Subsequence => 1,
    };
  }

}
