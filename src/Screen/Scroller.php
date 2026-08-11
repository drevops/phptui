<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

/**
 * Computes the visible window of a scrolling list.
 *
 * `follow()` keeps the cursor inside the viewport and `viewport()` resolves a
 * window for an offset alone. Both clamp to the valid range, and a viewport
 * reports ▲/▼.
 *
 * @package DrevOps\Tui\Screen
 */
class Scroller {

  /**
   * Compute the viewport that keeps the cursor visible.
   *
   * @param int $total
   *   The total number of lines.
   * @param int $height
   *   The viewport height.
   * @param int $cursor
   *   The cursor line index.
   * @param int $offset
   *   The current first-visible-line index.
   *
   * @return \DrevOps\Tui\Screen\Viewport
   *   The computed viewport.
   */
  public function follow(int $total, int $height, int $cursor, int $offset): Viewport {
    if ($height <= 0 || $total <= 0) {
      return new Viewport(0, FALSE, FALSE);
    }

    $cursor = max(0, min($total - 1, $cursor));

    if ($cursor < $offset) {
      $offset = $cursor;
    }
    elseif ($cursor >= $offset + $height) {
      $offset = $cursor - $height + 1;
    }

    return $this->viewport($offset, $total, $height);
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
   * @param int $height
   *   The viewport height.
   *
   * @return \DrevOps\Tui\Screen\Viewport
   *   The resolved viewport.
   */
  public function viewport(int $offset, int $total, int $height): Viewport {
    if ($height <= 0 || $total <= 0) {
      return new Viewport(0, FALSE, FALSE);
    }

    $offset = $this->clamp($offset, $total, $height);

    return new Viewport($offset, $offset > 0, $offset + $height < $total);
  }

  /**
   * Slice the visible lines for an offset and height.
   *
   * @param list<string> $lines
   *   The lines.
   * @param int $offset
   *   The first-visible-line index.
   * @param int $height
   *   The viewport height.
   *
   * @return list<string>
   *   The visible lines.
   */
  public function slice(array $lines, int $offset, int $height): array {
    return array_slice($lines, max(0, $offset), max(0, $height));
  }

  /**
   * Clamp an offset to the valid range.
   *
   * @param int $offset
   *   The offset.
   * @param int $total
   *   The total number of lines.
   * @param int $height
   *   The viewport height.
   *
   * @return int
   *   The clamped offset.
   */
  protected function clamp(int $offset, int $total, int $height): int {
    return max(0, min(max(0, $total - $height), $offset));
  }

}
