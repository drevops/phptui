<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block\Capability;

use DrevOps\PhpTui\Block\Mode;
use DrevOps\PhpTui\Input\Key;

/**
 * A block that opens in place to capture something, then closes again.
 *
 * One block owns both shapes: the settled one it draws on a single line, and
 * the open one that takes over the space right of its label. What is being
 * typed is a draft rather than an answer, which is why closing without
 * accepting leaves the answer where it was.
 *
 * Opening hands the space right of the label to something that collects, and
 * from then on the open shape is that thing: the keys reaching the block are
 * the keys it answers to, the draft is what it holds, and closing is what
 * decides whether any of it becomes the answer.
 *
 * @package DrevOps\PhpTui\Block\Capability
 */
interface CaptureCapableInterface {

  /**
   * Which of its two shapes this block is drawing.
   *
   * @return \DrevOps\PhpTui\Block\Mode
   *   The mode.
   */
  public function mode(): Mode;

  /**
   * Open this block to capture something.
   *
   * @return static
   *   The block.
   */
  public function open(): static;

  /**
   * Close this block, discarding whatever was being typed.
   *
   * @return static
   *   The block.
   */
  public function close(): static;

  /**
   * Set what is being typed, before it is accepted.
   *
   * @param mixed $draft
   *   The draft.
   *
   * @return static
   *   The block.
   */
  public function draft(mixed $draft): static;

  /**
   * Send a key to what this block opened onto.
   *
   * @param \DrevOps\PhpTui\Input\Key $key
   *   The key.
   *
   * @return bool
   *   TRUE when something took it, FALSE when nothing is open to take it.
   */
  public function capture(Key $key): bool;

}
