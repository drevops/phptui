<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\NumberBounds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the number bounds value object.
 */
#[CoversClass(NumberBounds::class)]
#[Group('block')]
final class NumberBoundsTest extends TestCase {

  #[DataProvider('dataProviderContains')]
  public function testContains(?int $min, ?int $max, int|float $value, bool $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->contains($value));
  }

  public static function dataProviderContains(): \Iterator {
    yield 'within both' => [1, 10, 5, TRUE];
    yield 'on lower bound' => [1, 10, 1, TRUE];
    yield 'on upper bound' => [1, 10, 10, TRUE];
    yield 'below both' => [1, 10, 0, FALSE];
    yield 'above both' => [1, 10, 11, FALSE];
    yield 'below open max' => [1, NULL, 0, FALSE];
    yield 'above open max' => [1, NULL, 99, TRUE];
    yield 'above open min' => [NULL, 10, 11, FALSE];
    yield 'below open min' => [NULL, 10, -5, TRUE];
    yield 'unbounded' => [NULL, NULL, 999, TRUE];
    yield 'float within' => [1, 10, 5.5, TRUE];
    yield 'float above' => [1, 10, 10.5, FALSE];
    yield 'float below' => [1, 10, 0.5, FALSE];
  }

  #[DataProvider('dataProviderViolation')]
  public function testViolation(?int $min, ?int $max, mixed $value, ?string $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->violation($value));
  }

  public static function dataProviderViolation(): \Iterator {
    yield 'int in range' => [1, 10, 5, NULL];
    yield 'int out of range' => [1, 10, 50, 'between 1 and 10'];
    yield 'float in range' => [1, 10, 5.5, NULL];
    yield 'float out of range' => [1, 10, 50.5, 'between 1 and 10'];
    yield 'min only violated' => [5, NULL, 1, 'at least 5'];
    yield 'max only violated' => [NULL, 5, 9, 'at most 5'];
    yield 'non-numeric string ignored' => [1, 10, 'oops', NULL];
    yield 'non-numeric bool ignored' => [1, 10, TRUE, NULL];
  }

  #[DataProvider('dataProviderDescribe')]
  public function testDescribe(?int $min, ?int $max, string $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->describe());
  }

  public static function dataProviderDescribe(): \Iterator {
    yield 'both' => [1, 10, 'between 1 and 10'];
    yield 'min only' => [1, NULL, 'at least 1'];
    yield 'max only' => [NULL, 10, 'at most 10'];
    yield 'neither' => [NULL, NULL, ''];
  }

  #[DataProvider('dataProviderClamp')]
  public function testClamp(?int $min, ?int $max, int $value, int $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max))->clamp($value));
  }

  public static function dataProviderClamp(): \Iterator {
    yield 'within' => [1, 10, 5, 5];
    yield 'below min' => [1, 10, -3, 1];
    yield 'above max' => [1, 10, 42, 10];
    yield 'open min below max' => [NULL, 10, 42, 10];
    yield 'open max above min' => [1, NULL, -3, 1];
    yield 'unbounded' => [NULL, NULL, 42, 42];
  }

  #[DataProvider('dataProviderStep')]
  public function testStep(?int $min, ?int $max, ?int $step, int $value, int $direction, int $expected): void {
    $this->assertSame($expected, (new NumberBounds($min, $max, $step))->step($value, $direction));
  }

  public static function dataProviderStep(): \Iterator {
    yield 'default step up' => [0, 10, NULL, 5, 1, 6];
    yield 'default step down' => [0, 10, NULL, 5, -1, 4];
    yield 'custom step up' => [0, 10, 3, 5, 1, 8];
    yield 'custom step up clamps to max' => [0, 10, 3, 9, 1, 10];
    yield 'custom step down clamps to min' => [0, 10, 3, 1, -1, 0];
    yield 'unbounded step up' => [NULL, NULL, 5, 100, 1, 105];
    yield 'snaps into range from below' => [5, 10, NULL, 0, 1, 5];
  }

}
