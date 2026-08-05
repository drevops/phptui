<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\TableSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the presentational table value object.
 */
#[CoversClass(TableSpec::class)]
#[Group('block')]
final class TableSpecTest extends TestCase {

  public function testKeepsStringCells(): void {
    $spec = new TableSpec(['Fruit', 'Colour'], [['Apple', 'Red'], ['Pear', 'Green']]);

    $this->assertSame(['Fruit', 'Colour'], $spec->headers);
    $this->assertSame([['Apple', 'Red'], ['Pear', 'Green']], $spec->rows);
  }

  public function testCoercesScalarCellsToStrings(): void {
    // Numbers and booleans stringify so tabular data need not be pre-formatted.
    $spec = new TableSpec(['Qty', 5], [['Apple', 3], ['Pear', TRUE]]);

    $this->assertSame(['Qty', '5'], $spec->headers);
    $this->assertSame([['Apple', '3'], ['Pear', '1']], $spec->rows);
  }

  public function testNonScalarCellsBecomeEmptyStrings(): void {
    $spec = new TableSpec(['H'], [[['nested'], NULL]]);

    $this->assertSame([['', '']], $spec->rows);
  }

  public function testEmptyHeadersAndRows(): void {
    $spec = new TableSpec([], []);

    $this->assertSame([], $spec->headers);
    $this->assertSame([], $spec->rows);
  }

}
