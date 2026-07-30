<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Translation\Translator;

/**
 * Optional integer bounds and step for a number field.
 *
 * Any of the three may be unset (NULL): an unset min or max leaves that side
 * open, and an unset step increments by one. The range arithmetic and the
 * human range phrase live here once, so the interactive field, the headless
 * engine and the answer-set validator all agree.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class NumberBounds {

  /**
   * Construct number bounds.
   *
   * @param int|null $min
   *   The inclusive minimum, or NULL for an open lower bound.
   * @param int|null $max
   *   The inclusive maximum, or NULL for an open upper bound.
   * @param int|null $step
   *   The Up/Down increment, or NULL to step by one.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When both bounds are declared and the minimum exceeds the maximum -
   *   mirroring the {@see DateBounds} constructor guard.
   */
  public function __construct(
    public ?int $min = NULL,
    public ?int $max = NULL,
    public ?int $step = NULL,
  ) {
    if ($this->min !== NULL && $this->max !== NULL && $this->min > $this->max) {
      throw new FormException(sprintf('Number bounds declare a minimum of %d above the maximum of %d.', $this->min, $this->max));
    }
  }

  /**
   * Whether a value is within the bounds.
   *
   * @param int|float $value
   *   The value.
   *
   * @return bool
   *   TRUE when the value is within both declared bounds.
   */
  public function contains(int|float $value): bool {
    if ($this->min !== NULL && $value < $this->min) {
      return FALSE;
    }

    return $this->max === NULL || $value <= $this->max;
  }

  /**
   * The range phrase for a value that violates the bounds, else NULL.
   *
   * The shared primitive behind every enforcement surface: it narrows the value
   * to a number (a non-numeric value is not this object's concern and passes),
   * then returns the human range phrase when the value falls outside the range.
   *
   * @param mixed $value
   *   The value to test.
   *
   * @return string|null
   *   The range phrase (e.g. "between 1 and 10") when out of range, else NULL.
   */
  public function violation(mixed $value): ?string {
    if (!is_int($value) && !is_float($value)) {
      return NULL;
    }

    return $this->contains($value) ? NULL : $this->describe();
  }

  /**
   * The human range phrase, e.g. "between 1 and 10" or "at least 1".
   *
   * @return string
   *   The phrase, or an empty string when neither bound is declared.
   */
  public function describe(): string {
    if ($this->min !== NULL && $this->max !== NULL) {
      return Translator::t('between @min and @max', ['@min' => $this->min, '@max' => $this->max]);
    }

    if ($this->min !== NULL) {
      return Translator::t('at least @min', ['@min' => $this->min]);
    }

    if ($this->max !== NULL) {
      return Translator::t('at most @max', ['@max' => $this->max]);
    }

    return '';
  }

  /**
   * Clamp a value into the declared bounds.
   *
   * @param int $value
   *   The value.
   *
   * @return int
   *   The value, moved onto the nearest bound it exceeds.
   */
  public function clamp(int $value): int {
    if ($this->min !== NULL && $value < $this->min) {
      return $this->min;
    }

    if ($this->max !== NULL && $value > $this->max) {
      return $this->max;
    }

    return $value;
  }

  /**
   * Step a value by the step in a direction, clamped to the bounds.
   *
   * @param int $value
   *   The current value.
   * @param int $direction
   *   Either 1 to increment or -1 to decrement.
   *
   * @return int
   *   The stepped, clamped value.
   */
  public function step(int $value, int $direction): int {
    return $this->clamp($value + $direction * ($this->step ?? 1));
  }

}
