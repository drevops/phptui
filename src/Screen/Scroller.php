<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen;

/**
 * Computes the visible window of a scrolling list.
 *
 * `follow()` keeps the cursor inside the viewport and `viewport()` resolves a
 * window for an offset alone. Both clamp to the valid range, and a viewport
 * reports ▲/▼.
 *
 * @package DrevOps\PhpTui\Screen
 */
class Scroller {

  /**
   * Compute the viewport that keeps the cursor visible.
   *
   * @param int $total
   *   The total number of lines.
   * @param int $rows
   *   The viewport height.
   * @param int $cursor
   *   The cursor line index.
   * @param int $offset
   *   The current first-visible-line index.
   *
   * @return \DrevOps\PhpTui\Screen\Viewport
   *   The computed viewport.
   */
  public function follow(int $total, int $rows, int $cursor, int $offset): Viewport {
    if ($rows <= 0 || $total <= 0) {
      return new Viewport(0, FALSE, FALSE);
    }

    $cursor = max(0, min($total - 1, $cursor));

    if ($cursor < $offset) {
      $offset = $cursor;
    }
    elseif ($cursor >= $offset + $rows) {
      $offset = $cursor - $rows + 1;
    }

    return $this->viewport($offset, $total, $rows);
  }

  /**
   * The viewport for an offset, clamped, with the scrolled-off flags resolved.
   *
   * The one place the scroll-indicator flags are decided.
   *
   * @param int $offset
   *   The desired first-visible-line index.
   * @param int $total
   *   The total number of lines.
   * @param int $rows
   *   The viewport height.
   *
   * @return \DrevOps\PhpTui\Screen\Viewport
   *   The resolved viewport.
   */
  public function viewport(int $offset, int $total, int $rows): Viewport {
    if ($rows <= 0 || $total <= 0) {
      return new Viewport(0, FALSE, FALSE);
    }

    $offset = $this->clamp($offset, $total, $rows);

    return new Viewport($offset, $offset > 0, $offset + $rows < $total);
  }

  /**
   * Slice the visible lines for an offset and height.
   *
   * @param list<string> $lines
   *   The lines.
   * @param int $offset
   *   The first-visible-line index.
   * @param int $rows
   *   The viewport height.
   *
   * @return list<string>
   *   The visible lines.
   */
  public function slice(array $lines, int $offset, int $rows): array {
    return array_slice($lines, max(0, $offset), max(0, $rows));
  }

  /**
   * Clamp an offset to the valid range.
   *
   * @param int $offset
   *   The offset.
   * @param int $total
   *   The total number of lines.
   * @param int $rows
   *   The viewport height.
   *
   * @return int
   *   The clamped offset.
   */
  protected function clamp(int $offset, int $total, int $rows): int {
    return max(0, min(max(0, $total - $rows), $offset));
  }

}
