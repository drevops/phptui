<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

/**
 * A scripted change of the terminal's size.
 *
 * Placed among the keystrokes a scripted terminal delivers, it makes the
 * terminal report the new size from that point on, as a window resized by
 * hand would.
 *
 * @package DrevOps\PhpTui\Testing
 */
final readonly class Resize {

  /**
   * Construct a resize.
   *
   * @param int $columns
   *   The width the terminal reports from now on, at least 1.
   * @param int $rows
   *   The height the terminal reports from now on, at least 1.
   *
   * @throws \InvalidArgumentException
   *   When either dimension is below 1.
   */
  public function __construct(public int $columns, public int $rows) {
    if ($columns < 1 || $rows < 1) {
      throw new \InvalidArgumentException('A resized terminal must be at least 1 column wide and 1 row high.');
    }
  }

}
