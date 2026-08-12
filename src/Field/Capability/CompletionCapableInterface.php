<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

/**
 * A field offering inline ghost-text completion of its buffer.
 *
 * {@see CompletionCapableTrait} carries the default implementation.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
interface CompletionCapableInterface {

  /**
   * The best completion candidate for the current buffer, if any.
   *
   * @return string|null
   *   The full candidate string, or NULL when nothing completes.
   */
  public function bestMatch(): ?string;

  /**
   * The ghost-text suffix shown after the caret, or an empty string when none.
   *
   * @return string
   *   The suffix of the best candidate beyond the typed buffer.
   */
  public function ghostSuffix(): string;

  /**
   * Fill the buffer with the current completion candidate, when one applies.
   */
  public function applyCompletion(): void;

}
