<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

use DrevOps\Tui\Translation\Translator;

/**
 * Optional min/max date range and the calendar's week-start day.
 *
 * Either bound may be unset (NULL) to leave that side open. The range
 * arithmetic, the strict ISO parsing and the human range phrase live here once,
 * so the interactive field, the headless engine and the answer-set validator
 * all agree. The week-start day is a display concern that rides along the way
 * the number field's keyboard step rides along in {@see NumberBounds}.
 *
 * @package DrevOps\Tui\Model
 */
final readonly class DateBounds {

  /**
   * Construct date bounds.
   *
   * @param \DateTimeImmutable|null $min
   *   The inclusive earliest date, or NULL for an open lower bound.
   * @param \DateTimeImmutable|null $max
   *   The inclusive latest date, or NULL for an open upper bound.
   * @param \DrevOps\Tui\Model\Weekday $weekStart
   *   The day the calendar week begins on.
   *
   * @throws \DrevOps\Tui\Model\FormException
   *   When both bounds are declared and the minimum falls after the maximum.
   */
  public function __construct(
    public ?\DateTimeImmutable $min = NULL,
    public ?\DateTimeImmutable $max = NULL,
    public Weekday $weekStart = Weekday::Monday,
  ) {
    if ($this->min instanceof \DateTimeImmutable && $this->max instanceof \DateTimeImmutable && $this->min > $this->max) {
      throw new FormException(sprintf('Date bounds declare a minimum of %s after the maximum of %s.', $this->min->format('Y-m-d'), $this->max->format('Y-m-d')));
    }
  }

  /**
   * Strictly parse an ISO `Y-m-d` date, or NULL when it is not a valid one.
   *
   * The parse is round-tripped through the same format, so an unpadded,
   * rolled-over or otherwise malformed value (e.g. "2026-2-3" or "2026-02-30")
   * is rejected rather than silently normalized.
   *
   * @param string $value
   *   The candidate date string.
   *
   * @return \DateTimeImmutable|null
   *   The parsed date at midnight, or NULL when the value is not a strict
   *   `Y-m-d` calendar date.
   */
  public static function parse(string $value): ?\DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : NULL;
  }

  /**
   * Whether a date is within the bounds.
   *
   * @param \DateTimeInterface $value
   *   The date.
   *
   * @return bool
   *   TRUE when the date is within both declared bounds.
   */
  public function contains(\DateTimeInterface $value): bool {
    if ($this->min instanceof \DateTimeImmutable && $value < $this->min) {
      return FALSE;
    }

    return !$this->max instanceof \DateTimeImmutable || $value <= $this->max;
  }

  /**
   * The range phrase for a date that violates the bounds, else NULL.
   *
   * Mirrors {@see NumberBounds::violation()}: a value that is not a strict date
   * string is not this object's concern and passes (the type check lives in the
   * schema validator), and an in-range date passes too.
   *
   * @param mixed $value
   *   The value to test.
   *
   * @return string|null
   *   The range phrase when out of range, else NULL.
   */
  public function violation(mixed $value): ?string {
    if (!is_string($value)) {
      return NULL;
    }

    $date = self::parse($value);
    if (!$date instanceof \DateTimeImmutable) {
      return NULL;
    }

    return $this->contains($date) ? NULL : $this->describe();
  }

  /**
   * The human range phrase, e.g. "between 2026-01-01 and 2026-12-31".
   *
   * @return string
   *   The phrase, or an empty string when neither bound is declared.
   */
  public function describe(): string {
    if ($this->min instanceof \DateTimeImmutable && $this->max instanceof \DateTimeImmutable) {
      return Translator::t('between @min and @max', [
        '@min' => $this->min->format('Y-m-d'),
        '@max' => $this->max->format('Y-m-d'),
      ]);
    }

    if ($this->min instanceof \DateTimeImmutable) {
      return Translator::t('on or after @min', ['@min' => $this->min->format('Y-m-d')]);
    }

    if ($this->max instanceof \DateTimeImmutable) {
      return Translator::t('on or before @max', ['@max' => $this->max->format('Y-m-d')]);
    }

    return '';
  }

  /**
   * Clamp a date onto the nearest bound it exceeds.
   *
   * @param \DateTimeImmutable $value
   *   The date.
   *
   * @return \DateTimeImmutable
   *   The date, moved onto the nearest bound it exceeds.
   */
  public function clamp(\DateTimeImmutable $value): \DateTimeImmutable {
    if ($this->min instanceof \DateTimeImmutable && $value < $this->min) {
      return $this->min;
    }

    if ($this->max instanceof \DateTimeImmutable && $value > $this->max) {
      return $this->max;
    }

    return $value;
  }

}
