<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the date bounds value object.
 */
#[CoversClass(DateBounds::class)]
#[Group('model')]
final class DateBoundsTest extends TestCase {

  #[DataProvider('dataProviderParse')]
  public function testParse(string $value, ?string $expected): void {
    $this->assertSame($expected, DateBounds::parse($value)?->format('Y-m-d'));
  }

  public static function dataProviderParse(): \Iterator {
    yield 'valid' => ['2026-07-15', '2026-07-15'];
    yield 'leap day' => ['2024-02-29', '2024-02-29'];
    yield 'unpadded rejected' => ['2026-7-5', NULL];
    yield 'rolled-over rejected' => ['2026-02-30', NULL];
    yield 'non-leap feb 29 rejected' => ['2026-02-29', NULL];
    yield 'invalid month rejected' => ['2026-13-01', NULL];
    yield 'garbage rejected' => ['not-a-date', NULL];
    yield 'empty rejected' => ['', NULL];
    yield 'trailing time rejected' => ['2026-07-15 10:00', NULL];
  }

  #[DataProvider('dataProviderContains')]
  public function testContains(?string $min, ?string $max, string $value, bool $expected): void {
    $this->assertSame($expected, $this->bounds($min, $max)->contains(new \DateTimeImmutable($value)));
  }

  public static function dataProviderContains(): \Iterator {
    yield 'within both' => ['2026-01-01', '2026-12-31', '2026-07-15', TRUE];
    yield 'on lower bound' => ['2026-01-01', '2026-12-31', '2026-01-01', TRUE];
    yield 'on upper bound' => ['2026-01-01', '2026-12-31', '2026-12-31', TRUE];
    yield 'before lower' => ['2026-01-01', '2026-12-31', '2025-12-31', FALSE];
    yield 'after upper' => ['2026-01-01', '2026-12-31', '2027-01-01', FALSE];
    yield 'before open max' => ['2026-01-01', NULL, '2025-12-31', FALSE];
    yield 'after open max' => ['2026-01-01', NULL, '2099-01-01', TRUE];
    yield 'after open min' => [NULL, '2026-12-31', '2027-01-01', FALSE];
    yield 'before open min' => [NULL, '2026-12-31', '1999-01-01', TRUE];
    yield 'unbounded' => [NULL, NULL, '2099-01-01', TRUE];
  }

  #[DataProvider('dataProviderViolation')]
  public function testViolation(?string $min, ?string $max, mixed $value, ?string $expected): void {
    $this->assertSame($expected, $this->bounds($min, $max)->violation($value));
  }

  public static function dataProviderViolation(): \Iterator {
    yield 'in range' => ['2026-01-01', '2026-12-31', '2026-07-15', NULL];
    yield 'out of range' => ['2026-01-01', '2026-12-31', '2027-01-01', 'between 2026-01-01 and 2026-12-31'];
    yield 'before min only' => ['2026-01-01', NULL, '2025-01-01', 'on or after 2026-01-01'];
    yield 'after max only' => [NULL, '2026-12-31', '2027-01-01', 'on or before 2026-12-31'];
    yield 'malformed ignored' => ['2026-01-01', '2026-12-31', 'not-a-date', NULL];
    yield 'empty ignored' => ['2026-01-01', '2026-12-31', '', NULL];
    yield 'non-string ignored' => ['2026-01-01', '2026-12-31', 42, NULL];
  }

  #[DataProvider('dataProviderDescribe')]
  public function testDescribe(?string $min, ?string $max, string $expected): void {
    $this->assertSame($expected, $this->bounds($min, $max)->describe());
  }

  public static function dataProviderDescribe(): \Iterator {
    yield 'both' => ['2026-01-01', '2026-12-31', 'between 2026-01-01 and 2026-12-31'];
    yield 'min only' => ['2026-01-01', NULL, 'on or after 2026-01-01'];
    yield 'max only' => [NULL, '2026-12-31', 'on or before 2026-12-31'];
    yield 'neither' => [NULL, NULL, ''];
  }

  #[DataProvider('dataProviderClamp')]
  public function testClamp(?string $min, ?string $max, string $value, string $expected): void {
    $this->assertSame($expected, $this->bounds($min, $max)->clamp(new \DateTimeImmutable($value))->format('Y-m-d'));
  }

  public static function dataProviderClamp(): \Iterator {
    yield 'within' => ['2026-01-01', '2026-12-31', '2026-07-15', '2026-07-15'];
    yield 'before min' => ['2026-01-01', '2026-12-31', '2025-01-01', '2026-01-01'];
    yield 'after max' => ['2026-01-01', '2026-12-31', '2027-01-01', '2026-12-31'];
    yield 'open min below max' => [NULL, '2026-12-31', '2027-01-01', '2026-12-31'];
    yield 'open max above min' => ['2026-01-01', NULL, '2025-01-01', '2026-01-01'];
    yield 'unbounded' => [NULL, NULL, '2099-01-01', '2099-01-01'];
  }

  public function testWeekStartDefaultsToMonday(): void {
    $this->assertSame(Weekday::Monday, (new DateBounds())->weekStart);
    $this->assertSame(Weekday::Sunday, (new DateBounds(weekStart: Weekday::Sunday))->weekStart);
  }

  public function testConstructorRejectsReversedBounds(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Date bounds declare a minimum of 2026-12-31 after the maximum of 2026-01-01.');

    new DateBounds(new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable('2026-01-01'));
  }

  /**
   * Build date bounds from optional ISO date strings.
   *
   * @param string|null $min
   *   The minimum date, or NULL for an open lower bound.
   * @param string|null $max
   *   The maximum date, or NULL for an open upper bound.
   */
  protected function bounds(?string $min, ?string $max): DateBounds {
    return new DateBounds(
      $min === NULL ? NULL : new \DateTimeImmutable($min),
      $max === NULL ? NULL : new \DateTimeImmutable($max),
    );
  }

}
