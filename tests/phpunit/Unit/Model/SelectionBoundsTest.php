<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Model;

use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Model\SelectionBounds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the selection bounds value object.
 */
#[CoversClass(SelectionBounds::class)]
#[Group('model')]
final class SelectionBoundsTest extends TestCase {

  #[DataProvider('dataProviderContains')]
  public function testContains(?int $min, ?int $max, int $count, bool $expected): void {
    $this->assertSame($expected, (new SelectionBounds($min, $max))->contains($count));
  }

  public static function dataProviderContains(): \Iterator {
    yield 'within both' => [2, 4, 3, TRUE];
    yield 'on lower bound' => [2, 4, 2, TRUE];
    yield 'on upper bound' => [2, 4, 4, TRUE];
    yield 'below both' => [2, 4, 1, FALSE];
    yield 'above both' => [2, 4, 5, FALSE];
    yield 'below open max' => [2, NULL, 1, FALSE];
    yield 'above open max' => [2, NULL, 9, TRUE];
    yield 'above open min' => [NULL, 3, 4, FALSE];
    yield 'below open min' => [NULL, 3, 0, TRUE];
    yield 'unbounded' => [NULL, NULL, 0, TRUE];
  }

  #[DataProvider('dataProviderViolation')]
  public function testViolation(?int $min, ?int $max, mixed $value, ?string $expected): void {
    $this->assertSame($expected, (new SelectionBounds($min, $max))->violation($value));
  }

  public static function dataProviderViolation(): \Iterator {
    yield 'list in range' => [2, 4, ['a', 'b', 'c'], NULL];
    yield 'list below min' => [2, 4, ['a'], 'between 2 and 4 items'];
    yield 'list above max' => [2, 4, ['a', 'b', 'c', 'd', 'e'], 'between 2 and 4 items'];
    yield 'empty below min' => [2, NULL, [], 'at least 2 items'];
    yield 'above max only' => [NULL, 3, ['a', 'b', 'c', 'd'], 'at most 3 items'];
    yield 'exactly violated' => [2, 2, ['a'], 'exactly 2 items'];
    yield 'non-array string ignored' => [2, 4, 'oops', NULL];
    yield 'non-array int ignored' => [2, 4, 5, NULL];
  }

  #[DataProvider('dataProviderDescribe')]
  public function testDescribe(?int $min, ?int $max, string $expected): void {
    $this->assertSame($expected, (new SelectionBounds($min, $max))->describe());
  }

  public static function dataProviderDescribe(): \Iterator {
    yield 'min only' => [2, NULL, 'at least 2 items'];
    yield 'min only singular' => [1, NULL, 'at least 1 item'];
    yield 'max only' => [NULL, 3, 'at most 3 items'];
    yield 'max only singular' => [NULL, 1, 'at most 1 item'];
    yield 'between' => [2, 4, 'between 2 and 4 items'];
    yield 'between from one' => [1, 3, 'between 1 and 3 items'];
    yield 'exactly' => [3, 3, 'exactly 3 items'];
    yield 'exactly singular' => [1, 1, 'exactly 1 item'];
    yield 'neither' => [NULL, NULL, ''];
  }

  #[DataProvider('dataProviderInvalid')]
  public function testConstructRejectsInvalidBounds(?int $min, ?int $max, string $message): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage($message);

    new SelectionBounds($min, $max);
  }

  public static function dataProviderInvalid(): \Iterator {
    yield 'min below one' => [0, NULL, 'Selection bounds declare a minimum of 0 below one.'];
    yield 'negative min' => [-2, NULL, 'Selection bounds declare a minimum of -2 below one.'];
    yield 'max below one' => [NULL, 0, 'Selection bounds declare a maximum of 0 below one.'];
    yield 'min above max' => [5, 2, 'Selection bounds declare a minimum of 5 above the maximum of 2.'];
  }

}
