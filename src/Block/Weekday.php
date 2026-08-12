<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Translation\Translator;

/**
 * A day of the week, backed by its ISO-8601 number (Monday = 1 ... Sunday = 7).
 *
 * The calendar's week-start day is one of these, and the field builds its
 * weekday header and column layout from the sequence starting at that day.
 *
 * @package DrevOps\PhpTui\Block
 */
enum Weekday: int {

  case Monday = 1;
  case Tuesday = 2;
  case Wednesday = 3;
  case Thursday = 4;
  case Friday = 5;
  case Saturday = 6;
  case Sunday = 7;

  /**
   * The two-letter column heading for this weekday.
   *
   * @return string
   *   The abbreviation, e.g. "Mo".
   */
  public function abbreviation(): string {
    return match ($this) {
      self::Monday => Translator::t('Mo'),
      self::Tuesday => Translator::t('Tu'),
      self::Wednesday => Translator::t('We'),
      self::Thursday => Translator::t('Th'),
      self::Friday => Translator::t('Fr'),
      self::Saturday => Translator::t('Sa'),
      self::Sunday => Translator::t('Su'),
    };
  }

  /**
   * The weekday a date falls on.
   *
   * @param \DateTimeInterface $date
   *   The date.
   *
   * @return self
   *   The weekday.
   */
  public static function fromDate(\DateTimeInterface $date): self {
    return self::from((int) $date->format('N'));
  }

  /**
   * The seven weekdays in display order, starting from this one.
   *
   * @return list<self>
   *   The rotated sequence of all seven weekdays.
   */
  public function sequence(): array {
    $out = [];

    for ($offset = 0; $offset < 7; $offset++) {
      $out[] = self::from(($this->value - 1 + $offset) % 7 + 1);
    }

    return $out;
  }

  /**
   * The zero-based column a weekday occupies in a week starting on this day.
   *
   * @param self $weekday
   *   The weekday to place.
   *
   * @return int
   *   The column index, 0 (the week-start day) to 6.
   */
  public function columnOf(self $weekday): int {
    return ($weekday->value - $this->value + 7) % 7;
  }

}
