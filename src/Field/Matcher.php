<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Utils\Strings;

/**
 * Ranks candidates against a query by fuzzy (subsequence) relevance.
 *
 * A candidate matches when the query's characters appear in it in order, not
 * necessarily adjacent - so "gha" matches "GitHub Actions". Matches are scored
 * in tiers (exact, prefix, substring, scattered subsequence) so a tighter match
 * always ranks ahead of a looser one, then refined within a tier by how early
 * and how contiguous the match is. Matching is case-insensitive and
 * multibyte-aware, and every match reports the character indices it hit so a
 * field can highlight them.
 *
 * The matcher is stateless: one instance serves every candidate and query.
 *
 * @package DrevOps\Tui\Field
 */
final class Matcher {

  /**
   * The tier multiplier: it exceeds the maximum refinement so tiers dominate.
   */
  protected const int TIER_WEIGHT = 1000;

  /**
   * Match a query against a candidate, scoring it and locating the hit.
   *
   * @param string $haystack
   *   The candidate text.
   * @param string $needle
   *   The query.
   *
   * @return \DrevOps\Tui\Field\MatchResult|null
   *   The result, or NULL when the query is not a subsequence of the candidate.
   *   An empty query matches everything with a zero score and no positions.
   */
  public function match(string $haystack, string $needle): ?MatchResult {
    if ($needle === '') {
      return new MatchResult(0, []);
    }

    // Fold each code point on its own, so a matched index maps straight back to
    // the original string even when lowercasing changes a character's length.
    $chars = Strings::split($haystack);
    $folded = array_map($this->fold(...), $chars);
    $needle_folded = array_map($this->fold(...), Strings::split($needle));

    $best = $this->bestSubsequence($folded, $needle_folded, $chars);
    if ($best === NULL) {
      return NULL;
    }

    [$positions, $refinement] = $best;

    // The tier compares the same folded characters the subsequence matched, so
    // the two stages can never disagree on context-sensitive case mappings.
    return new MatchResult($this->tier(implode('', $folded), implode('', $needle_folded))->weight() * self::TIER_WEIGHT + $refinement, $positions);
  }

  /**
   * Case-fold one character for matching.
   *
   * Lowercases the code point and maps the Greek final sigma onto the regular
   * sigma: lowercasing a capital sigma in isolation can never produce the
   * final form, so without the mapping a query typed in capitals could not
   * match a label ending in "ς".
   *
   * @param string $char
   *   The character.
   *
   * @return string
   *   The folded character.
   */
  protected function fold(string $char): string {
    return str_replace('ς', 'σ', Strings::lower($char));
  }

  /**
   * The matched character indices, or an empty list when there is no match.
   *
   * @param string $haystack
   *   The candidate text.
   * @param string $needle
   *   The query.
   *
   * @return list<int>
   *   The matched indices.
   */
  public function positions(string $haystack, string $needle): array {
    $result = $this->match($haystack, $needle);

    return $result instanceof MatchResult ? $result->positions : [];
  }

  /**
   * Filter and rank a list of string candidates by relevance, best first.
   *
   * @param list<string> $values
   *   The candidate values.
   * @param string $needle
   *   The query.
   *
   * @return list<string>
   *   The matching values, most relevant first; ties keep their input order.
   */
  public function rankValues(array $values, string $needle): array {
    return $this->rank($values, static fn(string $value): string => $value, $needle);
  }

  /**
   * Filter and rank option rows by label relevance, best first.
   *
   * Only selectable-or-disabled Option rows take part; separators and headings
   * carry no label and drop away, so the filtered result reads as a flat
   * relevance list.
   *
   * @param list<\DrevOps\Tui\Model\Option> $options
   *   The option rows.
   * @param string $needle
   *   The query.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The matching options, most relevant first; ties keep their input order.
   */
  public function rankOptions(array $options, string $needle): array {
    return $this->rank($options, static fn(Option $option): ?string => $option->kind === OptionKind::Option ? $option->label : NULL, $needle);
  }

  /**
   * Filter and rank items by the relevance of their text, best first.
   *
   * @param list<T> $items
   *   The candidate items.
   * @param \Closure(T): ?string $text
   *   Extracts an item's matchable text; NULL excludes the item outright.
   * @param string $needle
   *   The query.
   *
   * @return list<T>
   *   The matching items, most relevant first; ties keep their input order.
   *
   * @template T
   */
  protected function rank(array $items, \Closure $text, string $needle): array {
    $scores = [];

    foreach ($items as $index => $item) {
      $label = $text($item);
      if ($label === NULL) {
        continue;
      }

      $result = $this->match($label, $needle);
      if ($result instanceof MatchResult) {
        $scores[$index] = $result->score;
      }
    }

    // Sort by score, best first; uasort is stable, so equal scores keep their
    // insertion order - which is the input order.
    uasort($scores, static fn(int $left_score, int $right_score): int => $right_score <=> $left_score);

    $ranked = [];
    foreach (array_keys($scores) as $index) {
      $ranked[] = $items[$index];
    }

    return $ranked;
  }

  /**
   * The match tier: exact, prefix, substring or subsequence.
   *
   * @param string $haystack
   *   The case-folded candidate.
   * @param string $needle
   *   The case-folded query.
   *
   * @return \DrevOps\Tui\Field\MatchTier
   *   The tier.
   */
  protected function tier(string $haystack, string $needle): MatchTier {
    if ($haystack === $needle) {
      return MatchTier::Exact;
    }

    if (str_starts_with($haystack, $needle)) {
      return MatchTier::Prefix;
    }

    return str_contains($haystack, $needle) ? MatchTier::Substring : MatchTier::Subsequence;
  }

  /**
   * The best-scoring ordered embedding of the needle in the candidate.
   *
   * Every ordered way the needle's characters appear in the candidate is an
   * embedding; this returns the one the refinement rates highest - the
   * tightest, earliest, most word-boundary-aligned - so the score and the
   * highlighted characters always agree. The refinement rewards an early,
   * word-boundary start and penalises gaps between matched characters; because
   * that penalty is additive over consecutive characters, a short dynamic
   * program finds the best embedding in O(candidate * needle) time.
   *
   * @param list<string> $haystack
   *   The per-character-folded candidate.
   * @param list<string> $needle
   *   The per-character-folded query (non-empty).
   * @param list<string> $original
   *   The original candidate characters, for word-boundary tests.
   *
   * @return array{list<int>, int}|null
   *   The matched indices and the bounded refinement, or NULL when the needle
   *   is not a subsequence of the candidate.
   */
  protected function bestSubsequence(array $haystack, array $needle, array $original): ?array {
    $count = count($haystack);
    $depth = count($needle);

    // score[i] is the best path score placing the current needle character at
    // candidate index i; NULL marks an unreachable placement. back[j][i] holds
    // the predecessor index chosen there, for recovering the winning indices.
    $score = array_fill(0, $count, NULL);
    $back = [array_fill(0, $count, -1)];

    for ($i = 0; $i < $count; $i++) {
      if ($haystack[$i] === $needle[0]) {
        $score[$i] = ($this->isWordStart($i, $original) ? 50 : 0) - 10 * $i;
      }
    }

    for ($j = 1; $j < $depth; $j++) {
      $next = array_fill(0, $count, NULL);
      $step_back = array_fill(0, $count, -1);
      $running = NULL;
      $running_index = -1;

      for ($i = 0; $i < $count; $i++) {
        // Fold every predecessor p < i into a running best of score[p] + 20*p,
        // so the gap penalty -20*(i - p - 1) reduces to that running maximum.
        if ($i > 0 && $score[$i - 1] !== NULL) {
          $candidate = $score[$i - 1] + 20 * ($i - 1);
          if ($running === NULL || $candidate > $running) {
            $running = $candidate;
            $running_index = $i - 1;
          }
        }

        if ($running !== NULL && $haystack[$i] === $needle[$j]) {
          $next[$i] = 20 - 20 * $i + $running;
          $step_back[$i] = $running_index;
        }
      }

      $score = $next;
      $back[$j] = $step_back;
    }

    $best = NULL;
    $end = -1;
    foreach ($score as $index => $value) {
      if ($value !== NULL && ($best === NULL || $value > $best)) {
        $best = $value;
        $end = $index;
      }
    }

    if ($best === NULL) {
      return NULL;
    }

    $positions = [];
    $index = $end;
    for ($j = $depth - 1; $j >= 0; $j--) {
      $positions[] = $index;
      $index = $back[$j][$index];
    }

    return [array_reverse($positions), max(0, min(self::TIER_WEIGHT - 1, 500 + $best))];
  }

  /**
   * Whether an index begins a word (string start or after a non-alphanumeric).
   *
   * @param int $index
   *   The index into the candidate.
   * @param list<string> $haystack_chars
   *   The candidate characters.
   *
   * @return bool
   *   TRUE when the index starts a word.
   */
  protected function isWordStart(int $index, array $haystack_chars): bool {
    if ($index === 0) {
      return TRUE;
    }

    return preg_match('/[\p{L}\p{N}]/u', $haystack_chars[$index - 1] ?? '') !== 1;
  }

}
