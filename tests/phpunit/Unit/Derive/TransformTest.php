<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Derive;

use DrevOps\PhpTui\Derive\Transform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the value transforms.
 */
#[CoversClass(Transform::class)]
#[Group('derive')]
final class TransformTest extends TestCase {

  #[DataProvider('dataProviderApply')]
  public function testApply(string $name, string $input, string $expected): void {
    $this->assertSame($expected, Transform::apply($input, $name));
  }

  public static function dataProviderApply(): \Iterator {
    // A sample of the exposed str2name conversions - each transform's own
    // output is covered upstream, so this just checks apply() dispatches.
    yield 'machine' => ['machine', 'My Site! 2', 'my_site_2'];
    yield 'kebab' => ['kebab', 'My Site', 'my-site'];
    yield 'pascal' => ['pascal', 'my_site', 'MySite'];
    yield 'snake' => ['snake', 'My Site', 'my_site'];
    // An unknown transform passes the value through unchanged.
    yield 'unknown passthrough' => ['bogus', 'As Is', 'As Is'];
  }

  #[DataProvider('dataProviderSupports')]
  public function testSupports(string $name, bool $expected): void {
    $this->assertSame($expected, Transform::supports($name));
  }

  public static function dataProviderSupports(): \Iterator {
    yield 'machine (str2name)' => ['machine', TRUE];
    yield 'abbreviation (str2name)' => ['abbreviation', TRUE];
    yield 'unknown' => ['bogus', FALSE];
    yield 'empty' => ['', FALSE];
  }

  public function testNames(): void {
    $names = Transform::names();

    $this->assertContains('machine', $names);
    $this->assertNotContains('bogus', $names);
  }

}
