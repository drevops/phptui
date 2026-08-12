<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Derive;

use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\FormException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the derive rule object.
 */
#[CoversClass(Derive::class)]
#[Group('derive')]
final class DeriveTest extends TestCase {

  /**
   * Templates interpolate and transforms normalize.
   *
   * @param \DrevOps\PhpTui\Derive\Derive $derive
   *   The rule.
   * @param array<string,mixed> $values
   *   The current values.
   * @param string $expected
   *   The expected derived value.
   */
  #[DataProvider('dataProviderCompute')]
  public function testCompute(Derive $derive, array $values, string $expected): void {
    $this->assertSame($expected, $derive->compute($values));
  }

  /**
   * Data provider for testCompute().
   *
   * @return \Iterator<string,array{\DrevOps\PhpTui\Derive\Derive,array<string,mixed>,string}>
   *   Rules, values and expected results.
   */
  public static function dataProviderCompute(): \Iterator {
    yield 'plain interpolation' => [new Derive('{{name}}'), ['name' => 'Acme Site'], 'Acme Site'];
    yield 'spaced token' => [new Derive('{{ name }}.com'), ['name' => 'acme'], 'acme.com'];
    yield 'missing token resolves empty' => [new Derive('a-{{nope}}-b'), [], 'a--b'];
    yield 'non-scalar token resolves empty' => [new Derive('{{list}}'), ['list' => ['x']], ''];
    yield 'machine' => [new Derive('{{name}}', 'machine'), ['name' => 'My Site! 2'], 'my_site_2'];
    yield 'kebab (str2name)' => [new Derive('{{name}}', 'kebab'), ['name' => 'My Site'], 'my-site'];
  }

  public function testUnknownTransformThrows(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Unknown derive transform "bogus".');

    new Derive('{{name}}', 'bogus');
  }

  public function testToArray(): void {
    $this->assertSame(['template' => '{{name}}'], (new Derive('{{name}}'))->toArray());
    $this->assertSame(['template' => '{{name}}', 'transform' => 'machine'], (new Derive('{{name}}', 'machine'))->toArray());
  }

}
