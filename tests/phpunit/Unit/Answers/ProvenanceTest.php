<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Answers;

use DrevOps\PhpTui\Answers\Provenance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the provenance badge labels.
 */
#[CoversClass(Provenance::class)]
#[Group('answers')]
final class ProvenanceTest extends TestCase {

  #[DataProvider('dataProviderLabel')]
  public function testLabel(Provenance $provenance, string $expected): void {
    $this->assertSame($expected, $provenance->label());
  }

  /**
   * Data provider for testLabel().
   *
   * @return \Iterator<string,array{\DrevOps\PhpTui\Answers\Provenance,string}>
   *   Every provenance case and its English badge label.
   */
  public static function dataProviderLabel(): \Iterator {
    yield 'default' => [Provenance::Default, 'default'];
    yield 'detected' => [Provenance::Detected, 'detected'];
    yield 'edited' => [Provenance::Edited, 'edited'];
    yield 'derived' => [Provenance::Derived, 'derived'];
    yield 'override' => [Provenance::Override, 'override'];
  }

}
