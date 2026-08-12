<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Answers;

use DrevOps\PhpTui\Answers\ValueFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the shared value rendering.
 */
#[CoversClass(ValueFormatter::class)]
#[Group('answers')]
final class ValueFormatterTest extends TestCase {

  #[DataProvider('dataProviderFormat')]
  public function testFormat(mixed $value, string $expected): void {
    $this->assertSame($expected, ValueFormatter::format($value));
  }

  public static function dataProviderFormat(): \Iterator {
    yield 'true' => [TRUE, 'yes'];
    yield 'false' => [FALSE, 'no'];
    yield 'string' => ['pear', 'pear'];
    yield 'int' => [42, '42'];
    yield 'float' => [1.5, '1.5'];
    yield 'list' => [['apple', 'pear'], 'apple, pear'];
    yield 'list with non-scalars' => [['apple', ['nested']], 'apple, '];
    yield 'empty list' => [[], ''];
    yield 'null' => [NULL, ''];
    yield 'object' => [new \stdClass(), ''];
  }

  public function testMaskConcealsLength(): void {
    $this->assertSame('********', ValueFormatter::mask('*'));
    $this->assertSame(str_repeat('•', ValueFormatter::MASK_LENGTH), ValueFormatter::mask('•'));
  }

}
