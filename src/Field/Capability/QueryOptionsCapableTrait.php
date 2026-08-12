<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Translation\Translator;
use DrevOps\PhpTui\Utils\Strings;

/**
 * Candidate rows resolved from the live query rather than filtered locally.
 *
 * Holds everything about a query-sourced list that is not I/O. That covers
 * the resolved query, an in-flight flag, and what a failed or too-short query
 * shows instead of a list. The per-query cache serves a repeated query - a
 * backspace, a retyped prefix - without another call.
 *
 * The resolution itself belongs to the panel loop, which is the only place that
 * may block and repaint.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
trait QueryOptionsCapableTrait {

  /**
   * How many resolved queries are kept before the oldest is dropped.
   *
   * A session's queries are short-lived and short, so the cap stops unbounded
   * growth; memory pressure is not the concern.
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
   * The query the displayed rows were resolved for.
   *
   * NULL before the first resolution.
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
   * @var array<string,list<\DrevOps\PhpTui\Block\Option>>
   */
  protected array $queryCache = [];

  /**
   * Drive the field's rows from a query source.
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
   *   The query the rows were resolved for.
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows to show.
   */
  protected function settle(string $query, array $rows): void {
    $this->resolvedQuery = $query;
    $this->queryLoading = FALSE;
    $this->queryError = '';
    $this->adoptQueryRows($rows);
  }

  /**
   * Adopt a resolved query's rows as the field's candidates.
   *
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows.
   */
  abstract protected function adoptQueryRows(array $rows): void;

  /**
   * The line shown in place of the candidate list, when one applies.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
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

    $elements = $this->elements($theme);

    if ($this->queryLoading) {
      return $elements->fieldLoading();
    }

    if ($this->queryError !== '') {
      return $elements->fieldError($this->queryError);
    }

    if (Strings::length($this->query()) < $this->queryMinLength) {
      // Rendered as a constraint, not a description: the line shares its row
      // with the error above and states what the field expects.
      return $elements->fieldConstraint(Translator::formatPlural($this->queryMinLength, 'Type 1 character to search.', 'Type @count characters to search.'));
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
