<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;

/**
 * Candidate rows resolved from the live query rather than filtered locally.
 *
 * Holds everything about a query-sourced list that is not I/O: which query the
 * displayed rows answer, whether one is in flight, what a failed or too-short
 * query shows instead of a list, and the per-query cache that makes a repeat -
 * a backspace, a retyped prefix - free.
 *
 * The resolution itself belongs to the panel loop, which is the only place that
 * may block and repaint.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait QueryOptionsCapableTrait {

  /**
   * How many resolved queries are kept before the oldest is dropped.
   *
   * A session's queries are short-lived and short, so the cap is about never
   * growing without bound rather than about memory pressure.
   */
  public const int QUERY_CACHE_SIZE = 50;

  /**
   * Whether a query source is driving the rows.
   */
  protected bool $queryDriven = FALSE;

  /**
   * The query length below which the source is not called.
   */
  protected int $queryMinLength = 0;

  /**
   * The query the displayed rows answer, or NULL before the first resolution.
   */
  protected ?string $resolvedQuery = NULL;

  /**
   * Whether a call to the source is in flight.
   */
  protected bool $queryLoading = FALSE;

  /**
   * The message shown in place of the list after a failed query.
   */
  protected string $queryError = '';

  /**
   * The rows resolved for each query so far, oldest first.
   *
   * @var array<string,list<\DrevOps\Tui\Model\Option>>
   */
  protected array $queryCache = [];

  /**
   * Turn the field's rows over to a query source.
   *
   * @param int $min_length
   *   The query length below which the source is not called.
   */
  public function driveByQuery(int $min_length = 0): void {
    $this->queryDriven = TRUE;
    $this->queryMinLength = max(0, $min_length);
  }

  /**
   * {@inheritdoc}
   */
  public function isQueryDriven(): bool {
    return $this->queryDriven;
  }

  /**
   * {@inheritdoc}
   */
  public function pendingQuery(): ?string {
    if (!$this->queryDriven) {
      return NULL;
    }

    $query = $this->query();

    if ($query === $this->resolvedQuery) {
      return NULL;
    }

    if (Strings::length($query) < $this->queryMinLength) {
      $this->settle($query, []);

      return NULL;
    }

    if (isset($this->queryCache[$query])) {
      $this->settle($query, $this->queryCache[$query]);

      return NULL;
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function beginQuery(): void {
    $this->queryLoading = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function applyQuery(string $query, array $rows): void {
    $this->queryCache[$query] = $rows;

    if (count($this->queryCache) > self::QUERY_CACHE_SIZE) {
      array_shift($this->queryCache);
    }

    $this->settle($query, $rows);
  }

  /**
   * {@inheritdoc}
   */
  public function failQuery(string $query, string $message): void {
    $this->settle($query, []);
    $this->queryError = $message;
  }

  /**
   * Show a query's rows and leave the loading state.
   *
   * @param string $query
   *   The query the rows answer.
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows to show.
   */
  protected function settle(string $query, array $rows): void {
    $this->resolvedQuery = $query;
    $this->queryLoading = FALSE;
    $this->queryError = '';
    $this->adoptQueryRows($rows);
  }

  /**
   * Take on a resolved query's rows as the field's own candidates.
   *
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows.
   */
  abstract protected function adoptQueryRows(array $rows): void;

  /**
   * The line shown in place of the candidate list, when one applies.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string|null
   *   The loading indicator, the failure message or the prompt to keep typing;
   *   NULL when the list itself should be drawn.
   */
  protected function queryStateLine(ThemeInterface $theme): ?string {
    if (!$this->queryDriven) {
      return NULL;
    }

    if ($this->queryLoading) {
      // The same indicator a lazily loaded field shows in its panel row, so
      // waiting reads the same wherever it happens.
      return $theme->renderLoading('');
    }

    if ($this->queryError !== '') {
      return $theme->error($this->queryError);
    }

    if (Strings::length($this->query()) < $this->queryMinLength) {
      // The guidance voice, not the description's: this line shares its row
      // with the error above, and states what the field expects.
      return $theme->renderGuidance(Translator::formatPlural($this->queryMinLength, 'Type 1 character to search.', 'Type @count characters to search.'));
    }

    return NULL;
  }

  /**
   * The query the user has typed so far.
   *
   * @return string
   *   The live query.
   */
  abstract public function query(): string;

}
