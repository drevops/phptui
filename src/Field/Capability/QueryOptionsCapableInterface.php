<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field whose candidate list is resolved from its live query.
 *
 * The field owns the query, the cache and the displayed state. It never
 * resolves anything itself: a resolution blocks, and only the panel loop may
 * block and repaint. The loop reads {@see pendingQuery()} for the query still
 * needing resolution, calls the field's source, and passes the result back.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface QueryOptionsCapableInterface {

  /**
   * Drive the field's candidate list from a query source.
   *
   * @param int $min_length
   *   The query length below which the source is not called, so a short query
   *   does not request the backend's full listing; zero calls the source for
   *   the empty query too.
   */
  public function driveByQuery(int $min_length = 0): void;

  /**
   * Whether the field's candidates come from a query source.
   *
   * @return bool
   *   TRUE when a source is driving the list.
   */
  public function isQueryDriven(): bool;

  /**
   * The query the user has typed so far.
   *
   * @return string
   *   The live query, empty before anything is typed.
   */
  public function query(): string;

  /**
   * The query that still requires a call to the source, if any.
   *
   * A query requiring no call is settled directly: one unchanged, one below
   * the minimum length, one already resolved in this session. A NULL return
   * means the displayed list is up to date.
   *
   * @return string|null
   *   The query to resolve, or NULL when nothing needs resolving.
   */
  public function pendingQuery(): ?string;

  /**
   * Enter the loading state, so the next frame shows the indicator.
   */
  public function beginQuery(): void;

  /**
   * Adopt a query's resolved rows and leave the loading state.
   *
   * @param string $query
   *   The query that was resolved.
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows the source returned for it.
   */
  public function applyQuery(string $query, array $rows): void;

  /**
   * Record that a query could not be resolved and leave the loading state.
   *
   * @param string $query
   *   The query that failed, recorded so the same failing call is not
   *   repeated on every frame.
   * @param string $message
   *   The message shown in place of the list.
   */
  public function failQuery(string $query, string $message): void;

}
