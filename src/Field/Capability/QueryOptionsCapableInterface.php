<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

/**
 * A field whose candidate list is resolved from its live query.
 *
 * The field owns the query, the cache and the displayed state; it never
 * resolves anything itself, because a resolution blocks and only the panel loop
 * may block and repaint. The loop asks {@see pendingQuery()} what still needs
 * answering, calls the field's source, and hands the result back.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface QueryOptionsCapableInterface {

  /**
   * Turn the field's candidates over to a query source.
   *
   * @param int $min_length
   *   The query length below which the source is not called, so a backend is
   *   not asked to list everything; zero calls it for the empty query too.
   */
  public function driveByQuery(int $min_length = 0): void;

  /**
   * Whether the field's candidates come from a query source at all.
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
   * The query still waiting on a call to the source, if any.
   *
   * Settles everything that needs no call - an unchanged query, a query below
   * the minimum length, one already answered in this session - so a NULL return
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
   * @param list<\DrevOps\Tui\Block\Option> $rows
   *   The rows the source returned for it.
   */
  public function applyQuery(string $query, array $rows): void;

  /**
   * Record that a query could not be resolved and leave the loading state.
   *
   * @param string $query
   *   The query that failed - remembered, so the same failing call is not
   *   repeated on every frame.
   * @param string $message
   *   The message shown in place of the list.
   */
  public function failQuery(string $query, string $message): void;

}
