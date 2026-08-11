<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

/**
 * A field that edits a character buffer.
 *
 * {@see TextEditCapableTrait} carries the default cursor-based implementation;
 * an append-only field may implement the methods directly.
 *
 * @package DrevOps\Tui\Field\Capability
 */
interface TextEditCapableInterface {

  /**
   * The live input buffer.
   *
   * @return string
   *   The buffer.
   */
  public function buffer(): string;

  /**
   * Insert text at the editing position.
   *
   * @param string $text
   *   The text to insert.
   */
  public function insert(string $text): void;

  /**
   * Delete the character before the editing position.
   */
  public function backspace(): void;

}
