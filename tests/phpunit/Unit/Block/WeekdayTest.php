<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the weekday enum.
 */
#[CoversClass(Weekday::class)]
#[Group('block')]
final class WeekdayTest extends TestCase {

  #[DataProvider('dataProviderAbbreviation')]
  public function testAbbreviation(Weekday $weekday, string $expected): void {
    $this->assertSame($expected, $weekday->abbreviation());
  }

  public static function dataProviderAbbreviation(): \Iterator {
    yield 'monday' => [Weekday::Monday, 'Mo'];
    yield 'tuesday' => [Weekday::Tuesday, 'Tu'];
    yield 'wednesday' => [Weekday::Wednesday, 'We'];
    yield 'thursday' => [Weekday::Thursday, 'Th'];
    yield 'friday' => [Weekday::Friday, 'Fr'];
    yield 'saturday' => [Weekday::Saturday, 'Sa'];
    yield 'sunday' => [Weekday::Sunday, 'Su'];
  }

  #[DataProvider('dataProviderFromDate')]
  public function testFromDate(string $date, Weekday $expected): void {
    $this->assertSame($expected, Weekday::fromDate(new \DateTimeImmutable($date)));
  }

  public static function dataProviderFromDate(): \Iterator {
    // 2026-07-13 is a Monday; the week runs to Sunday 2026-07-19.
    yield 'monday' => ['2026-07-13', Weekday::Monday];
    yield 'wednesday' => ['2026-07-15', Weekday::Wednesday];
    yield 'sunday' => ['2026-07-19', Weekday::Sunday];
  }

  #[DataProvider('dataProviderSequence')]
  public function testSequence(Weekday $start, array $expected): void {
    $this->assertSame($expected, array_map(static fn(Weekday $day): string => $day->abbreviation(), $start->sequence()));
  }

  public static function dataProviderSequence(): \Iterator {
    yield 'from monday' => [Weekday::Monday, ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']];
    yield 'from sunday' => [Weekday::Sunday, ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']];
    yield 'from saturday' => [Weekday::Saturday, ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr']];
  }

  #[DataProvider('dataProviderColumnOf')]
  public function testColumnOf(Weekday $start, Weekday $weekday, int $expected): void {
    $this->assertSame($expected, $start->columnOf($weekday));
  }

  public static function dataProviderColumnOf(): \Iterator {
    yield 'monday start, monday' => [Weekday::Monday, Weekday::Monday, 0];
    yield 'monday start, wednesday' => [Weekday::Monday, Weekday::Wednesday, 2];
    yield 'monday start, sunday' => [Weekday::Monday, Weekday::Sunday, 6];
    yield 'sunday start, sunday' => [Weekday::Sunday, Weekday::Sunday, 0];
    yield 'sunday start, monday' => [Weekday::Sunday, Weekday::Monday, 1];
    yield 'sunday start, wednesday' => [Weekday::Sunday, Weekday::Wednesday, 3];
  }

}
