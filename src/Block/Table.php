<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Primitive\Element\PrimitiveElementsInterface;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * A grid whose rows are replaced between frames, with one selected row.
 *
 * Each row is addressed by a key, so the selection is a key rather than a
 * position: replacing the rows keeps it on the row with the same key wherever
 * that row now sits.
 *
 * The block takes no focus and binds no key. The selection is moved by the
 * code holding the block.
 *
 * @package DrevOps\PhpTui\Block
 */
final class Table extends AbstractBlock {

  /**
   * The header cells and body rows, coerced to strings.
   */
  protected TableSpec $spec;

  /**
   * The key of each body row, in the order the rows are drawn.
   *
   * @var list<string>
   */
  protected array $keys = [];

  /**
   * The key of the selected row, or NULL when none is selected.
   */
  protected ?string $selected = NULL;

  /**
   * Construct a table block.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param list<mixed> $headers
   *   The header cells; empty draws the grid with no header row.
   */
  public function __construct(
    protected string $id,
    array $headers = [],
  ) {
    $this->spec = new TableSpec($headers, []);
  }

  /**
   * The id this block is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Set the header cells.
   *
   * @param list<mixed> $headers
   *   The header cells; empty draws the grid with no header row.
   *
   * @return static
   *   The block.
   */
  public function headers(array $headers): static {
    $this->spec = new TableSpec($headers, $this->spec->rows);

    return $this;
  }

  /**
   * The header cells.
   *
   * @return list<string>
   *   The cells, empty when the grid has no header row.
   */
  public function headerCells(): array {
    return $this->spec->headers;
  }

  /**
   * Replace every body row.
   *
   * The selection stays on the row with the same key. When that key is gone,
   * it moves to the row now at the position the selected row held, or to the
   * last row when fewer remain; with no rows left nothing is selected.
   *
   * @param array<int|string,list<mixed>> $rows
   *   The rows keyed by row key, in the order they are drawn.
   *
   * @return static
   *   The block.
   */
  public function rows(array $rows): static {
    $position = $this->position();
    // PHP turns a numeric-string key into an integer, so keys are compared as
    // strings whichever way they arrived.
    $this->keys = array_map(strval(...), array_keys($rows));
    $this->spec = new TableSpec($this->spec->headers, array_values($rows));

    if ($this->selected === NULL || in_array($this->selected, $this->keys, TRUE)) {
      return $this;
    }

    $this->selected = $this->keys === [] ? NULL : $this->keys[min((int) $position, count($this->keys) - 1)];

    return $this;
  }

  /**
   * The body rows, keyed by row key.
   *
   * @return array<string,list<string>>
   *   The rows in the order they are drawn.
   */
  public function rowCells(): array {
    $rows = [];

    foreach ($this->keys as $index => $key) {
      $rows[$key] = $this->spec->rows[$index];
    }

    return $rows;
  }

  /**
   * Select the row with a key, or clear the selection.
   *
   * @param int|string|null $key
   *   The row key, or NULL to select nothing.
   *
   * @return static
   *   The block.
   *
   * @throws \InvalidArgumentException
   *   When no row has the key.
   */
  public function select(int|string|null $key): static {
    if ($key === NULL) {
      $this->selected = NULL;

      return $this;
    }

    if (!in_array((string) $key, $this->keys, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Table "%s" has no row with key "%s" to select.', $this->id, $key));
    }

    $this->selected = (string) $key;

    return $this;
  }

  /**
   * Move the selection by a number of rows, stopping at the first and last.
   *
   * With nothing selected, the first row is selected whichever way the
   * selection was moved.
   *
   * @param int $delta
   *   The rows to move by: negative towards the first row.
   *
   * @return static
   *   The block.
   */
  public function move(int $delta): static {
    if ($this->keys === []) {
      return $this;
    }

    $position = $this->position();
    $target = $position === NULL ? 0 : max(0, min(count($this->keys) - 1, $position + $delta));
    $this->selected = $this->keys[$target];

    return $this;
  }

  /**
   * The key of the selected row.
   *
   * @return string|null
   *   The key, or NULL when nothing is selected.
   */
  public function selected(): ?string {
    return $this->selected;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $pieces = $this->elements($theme, PrimitiveElementsInterface::class, 'a table');

    // An unselected grid still passes an index, so the selector gutter stays
    // in place and the grid does not shift when a row is selected.
    return implode("\n", $pieces->renderTable($this->spec->headers, $this->spec->rows, $this->position() ?? -1));
  }

  /**
   * Where the selected row sits among the rows.
   *
   * @return int|null
   *   The index, or NULL when nothing is selected.
   */
  protected function position(): ?int {
    $index = $this->selected === NULL ? FALSE : array_search($this->selected, $this->keys, TRUE);

    return $index === FALSE ? NULL : $index;
  }

}
