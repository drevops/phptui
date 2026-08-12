<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Utils\Strings;

/**
 * Inline ghost-text completion over a character buffer.
 *
 * Composes with {@see TextEditCapableTrait}: the buffer is completed to the
 * first candidate it is a case-insensitive prefix of. The candidate list, the
 * availability check and the acceptance step are each overridable. A field
 * whose buffer is not a plain caret line reuses the matching rule without
 * inheriting the caret arithmetic.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait CompletionCapableTrait {

  /**
   * The best completion candidate for the current buffer, if any.
   *
   * A candidate qualifies only when the buffer is non-empty, the field is in a
   * state that offers a completion, and the buffer is a case-insensitive prefix
   * of a strictly longer candidate; the first such candidate wins. Returns NULL
   * when nothing completes, so the field behaves as a plain text input.
   *
   * @return string|null
   *   The full candidate string, or NULL.
   */
  public function bestMatch(): ?string {
    if ($this->buffer === '' || !$this->isCompletionAvailable()) {
      return NULL;
    }

    // Fold and measure by character, not byte, so non-ASCII candidates match
    // case-insensitively and the suffix never splits mid-character.
    $needle = Strings::lower($this->buffer);
    $length = Strings::length($this->buffer);

    foreach ($this->completionCandidates() as $completion) {
      if (Strings::length($completion) > $length && str_starts_with(Strings::lower($completion), $needle)) {
        return $completion;
      }
    }

    return NULL;
  }

  /**
   * Whether the field's current state offers a completion.
   *
   * @return bool
   *   TRUE when the caret sits at the end of the buffer, so the ghost text
   *   continues the typed text instead of interrupting it.
   */
  protected function isCompletionAvailable(): bool {
    return $this->cursor === Strings::length($this->buffer);
  }

  /**
   * The candidates the buffer is matched against, in preference order.
   *
   * @return list<string>
   *   The candidate strings; empty when nothing completes.
   */
  abstract protected function completionCandidates(): array;

  /**
   * Set the buffer to an accepted candidate.
   *
   * @param string $match
   *   The candidate to complete the buffer to.
   */
  protected function completeBuffer(string $match): void {
    $this->buffer = $match;
    $this->cursor = Strings::length($match);
  }

  /**
   * The ghost-text suffix shown after the caret, or an empty string when none.
   *
   * @return string
   *   The suffix of the best candidate beyond the typed buffer.
   */
  public function ghostSuffix(): string {
    $match = $this->bestMatch();

    return $match === NULL ? '' : Strings::substr($match, Strings::length($this->buffer));
  }

  /**
   * Fill the buffer with the current completion candidate, when one applies.
   */
  public function applyCompletion(): void {
    $match = $this->bestMatch();

    if ($match !== NULL) {
      $this->completeBuffer($match);
    }
  }

}
