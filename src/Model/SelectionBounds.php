<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Translation\Translator;

/**
 * Optional minimum and maximum selection counts for a multi-value field.
 *
 * Either bound may be unset (NULL), leaving that side open. The count
 * arithmetic and the human phrase live here once, so the interactive widget,
 * the headless engine and the answer-set validator all agree - mirroring
 * {@see NumberBounds}, but constraining how many values a list holds rather
 * than the magnitude of a single number.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class SelectionBounds {

  /**
   * Construct selection bounds.
   *
   * @param int|null $min
   *   The inclusive minimum number of selections, or NULL for no floor.
   * @param int|null $max
   *   The inclusive maximum number of selections, or NULL for no ceiling.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When a declared bound is below one (a count bound below one is
   *   meaningless; omit the bound instead), or the minimum exceeds the maximum.
   */
  public function __construct(
    public ?int $min = NULL,
    public ?int $max = NULL,
  ) {
    if ($this->min !== NULL && $this->min < 1) {
      throw new FormException(sprintf('Selection bounds declare a minimum of %d below one.', $this->min));
    }

    if ($this->max !== NULL && $this->max < 1) {
      throw new FormException(sprintf('Selection bounds declare a maximum of %d below one.', $this->max));
    }

    if ($this->min !== NULL && $this->max !== NULL && $this->min > $this->max) {
      throw new FormException(sprintf('Selection bounds declare a minimum of %d above the maximum of %d.', $this->min, $this->max));
    }
  }

  /**
   * Whether a selection count is within the bounds.
   *
   * @param int $count
   *   The number of selections.
   *
   * @return bool
   *   TRUE when the count is within both declared bounds.
   */
  public function contains(int $count): bool {
    if ($this->min !== NULL && $count < $this->min) {
      return FALSE;
    }

    return $this->max === NULL || $count <= $this->max;
  }

  /**
   * The count phrase for a value that violates the bounds, else NULL.
   *
   * The shared primitive behind every enforcement surface: it narrows the value
   * to a list (a non-list value is not this object's concern and passes), then
   * returns the human count phrase when the number of items falls out of range.
   *
   * @param mixed $value
   *   The value to test.
   *
   * @return string|null
   *   The count phrase (e.g. "at least 2 items") when out of range, else NULL.
   */
  public function violation(mixed $value): ?string {
    if (!is_array($value)) {
      return NULL;
    }

    return $this->contains(count($value)) ? NULL : $this->describe();
  }

  /**
   * The human count phrase, e.g. "at least 2 items" or "exactly 3 items".
   *
   * @return string
   *   The phrase, or an empty string when neither bound is declared.
   */
  public function describe(): string {
    if ($this->min !== NULL && $this->max !== NULL) {
      if ($this->min === $this->max) {
        return Translator::formatPlural($this->min, 'exactly 1 item', 'exactly @count items');
      }

      // A range spans two distinct bounds, so the noun is always plural.
      return Translator::t('between @min and @max items', ['@min' => $this->min, '@max' => $this->max]);
    }

    if ($this->min !== NULL) {
      return Translator::formatPlural($this->min, 'at least 1 item', 'at least @count items');
    }

    if ($this->max !== NULL) {
      return Translator::formatPlural($this->max, 'at most 1 item', 'at most @count items');
    }

    return '';
  }

}
