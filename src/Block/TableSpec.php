<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Terminal\Ansi;

/**
 * A presentational table: header cells and body rows, coerced to strings.
 *
 * The cells are stored as plain strings; how they are styled and interpolated
 * is the renderer's concern, not this value object's.
 *
 * @package DrevOps\Tui\Block
 */
final readonly class TableSpec {

  /**
   * The header cells.
   *
   * @var list<string>
   */
  public array $headers;

  /**
   * The body rows.
   *
   * @var list<list<string>>
   */
  public array $rows;

  /**
   * Construct a table spec, coercing every cell to a string.
   *
   * A scalar cell (a number, a bool) is stringified so tabular data need not
   * be pre-formatted. A non-scalar cell becomes an empty string, so the
   * renderer only ever handles strings.
   *
   * An empty header list renders the grid with no header row.
   *
   * @param list<mixed> $headers
   *   The header cells.
   * @param list<list<mixed>> $rows
   *   The body rows, each a list of cells.
   */
  public function __construct(array $headers, array $rows) {
    $this->headers = self::cells($headers);

    $normalized = [];
    foreach ($rows as $row) {
      $normalized[] = self::cells($row);
    }

    $this->rows = $normalized;
  }

  /**
   * Coerce a row of cells to a list of strings.
   *
   * @param list<mixed> $cells
   *   The cells.
   *
   * @return list<string>
   *   The cells as strings; a non-scalar cell becomes an empty string.
   */
  protected static function cells(array $cells): array {
    $out = [];

    foreach ($cells as $cell) {
      $out[] = is_scalar($cell) ? Ansi::sanitize((string) $cell) : '';
    }

    return $out;
  }

}
