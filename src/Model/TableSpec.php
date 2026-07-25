<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * A presentational table: header cells and body rows.
 *
 * A note carries one to render an aligned grid beneath its title and body. The
 * data is plain strings; how it is styled and interpolated is the renderer's
 * concern, not this value object's.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class TableSpec {

  /**
   * Construct a table spec.
   *
   * @param list<string> $headers
   *   The header cells; an empty list renders the grid with no header row.
   * @param list<list<string>> $rows
   *   The body rows, each a list of cell strings.
   */
  public function __construct(public array $headers, public array $rows) {
  }

}
