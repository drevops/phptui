<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Block;

use DrevOps\PhpTui\Block\Table;
use DrevOps\PhpTui\Block\TableSpec;
use DrevOps\PhpTui\Tests\Fixtures\Theme\BlankTheme;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the table block: keyed rows replaced between frames, one selected.
 */
#[CoversClass(Table::class)]
#[CoversClass(TableSpec::class)]
#[Group('block')]
final class TableTest extends TestCase {

  public function testTableHandsBackItsIdHeadersAndRows(): void {
    $table = (new Table('orders', ['Fruit', 'Crates']))->rows(['apple' => ['Apple', 12], 'pear' => ['Pear', 5]]);

    $this->assertSame('orders', $table->id());
    $this->assertSame(['Fruit', 'Crates'], $table->headerCells());
    $this->assertSame(['apple' => ['Apple', '12'], 'pear' => ['Pear', '5']], $table->rowCells());
    $this->assertSame(['Produce'], $table->headers(['Produce'])->headerCells());
    $this->assertSame(['apple' => ['Apple', '12'], 'pear' => ['Pear', '5']], $table->rowCells());
  }

  public function testTableSelectsNothingUntilToldTo(): void {
    $this->assertNull((new Table('orders'))->rows(['apple' => ['Apple']])->selected());
  }

  #[DataProvider('dataProviderReplacingRowsKeepsSelectionOnSameKey')]
  public function testReplacingRowsKeepsSelectionOnSameKey(array $before, int|string $key, array $after, ?string $expected): void {
    $table = (new Table('orders'))->rows($before)->select($key);

    $this->assertSame($expected, $table->rows($after)->selected());
  }

  public static function dataProviderReplacingRowsKeepsSelectionOnSameKey(): array {
    $three = ['apple' => ['Apple'], 'pear' => ['Pear'], 'plum' => ['Plum']];

    return [
      'same rows' => [$three, 'pear', $three, 'pear'],
      'reordered' => [$three, 'pear', ['plum' => ['Plum'], 'apple' => ['Apple'], 'pear' => ['Pear']], 'pear'],
      'row inserted above' => [$three, 'pear', ['fig' => ['Fig'], 'apple' => ['Apple'], 'pear' => ['Pear'], 'plum' => ['Plum']], 'pear'],
      'cells changed' => [$three, 'pear', ['apple' => ['Apple'], 'pear' => ['Pear, ripe'], 'plum' => ['Plum']], 'pear'],
      'numeric key as int then string' => [[10 => ['Apple'], 20 => ['Pear']], 20, ['20' => ['Pear'], '30' => ['Plum']], '20'],
      'numeric key as string' => [['10' => ['Apple'], '20' => ['Pear']], '10', [10 => ['Apple']], '10'],
      'gone, falls to same position' => [$three, 'pear', ['apple' => ['Apple'], 'plum' => ['Plum'], 'fig' => ['Fig']], 'plum'],
      'gone, falls to last row' => [$three, 'plum', ['apple' => ['Apple'], 'pear' => ['Pear']], 'pear'],
      'gone, no rows left' => [$three, 'pear', [], NULL],
    ];
  }

  public function testReplacingRowsLeavesNoSelectionUnselected(): void {
    $this->assertNull((new Table('orders'))->rows(['apple' => ['Apple']])->rows(['pear' => ['Pear']])->selected());
  }

  public function testSelectingNullClearsSelection(): void {
    $this->assertNull((new Table('orders'))->rows(['apple' => ['Apple']])->select('apple')->select(NULL)->selected());
  }

  public function testSelectingKeyNoRowHasThrows(): void {
    $table = (new Table('orders'))->rows(['apple' => ['Apple']]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Table "orders" has no row with key "melon" to select.');

    $table->select('melon');
  }

  #[DataProvider('dataProviderMoveStepsSelectionAndStopsAtEnds')]
  public function testMoveStepsSelectionAndStopsAtEnds(?string $from, int $delta, string $expected): void {
    $table = (new Table('orders'))->rows(['apple' => ['Apple'], 'pear' => ['Pear'], 'plum' => ['Plum']])->select($from);

    $this->assertSame($expected, $table->move($delta)->selected());
  }

  public static function dataProviderMoveStepsSelectionAndStopsAtEnds(): array {
    return [
      'down one' => ['apple', 1, 'pear'],
      'up one' => ['plum', -1, 'pear'],
      'past the last' => ['pear', 5, 'plum'],
      'past the first' => ['pear', -5, 'apple'],
      'nothing selected, down' => [NULL, 1, 'apple'],
      'nothing selected, up' => [NULL, -1, 'apple'],
    ];
  }

  public function testMoveWithNoRowsSelectsNothing(): void {
    $this->assertNull((new Table('orders'))->move(1)->selected());
  }

  public function testTableMarksOnlySelectedRowUnderItsHeader(): void {
    $table = (new Table('orders', ['Fruit', 'Crates']))->rows(['apple' => ['Apple', 12], 'pear' => ['Pear', 5]])->select('pear');

    $this->assertSame([
      '  ╭───────┬────────╮',
      '  │ Fruit │ Crates │',
      '  ├───────┼────────┤',
      '  │ Apple │ 12     │',
      '❯ │ Pear  │ 5      │',
      '  ╰───────┴────────╯',
    ], explode("\n", $table->render($this->theme())));
  }

  public function testTableWithNoHeaderMarksSelectedRow(): void {
    $table = (new Table('orders'))->rows(['apple' => ['Apple'], 'pear' => ['Pear']])->select('apple');

    $this->assertSame([
      '  ╭───────╮',
      '❯ │ Apple │',
      '  │ Pear  │',
      '  ╰───────╯',
    ], explode("\n", $table->render($this->theme())));
  }

  public function testUnselectedTableKeepsTheSelectorGutter(): void {
    $table = (new Table('orders'))->rows(['apple' => ['Apple']]);

    $this->assertSame(['  ╭───────╮', '  │ Apple │', '  ╰───────╯'], explode("\n", $table->render($this->theme())));
  }

  public function testEmptyTableDrawsNothing(): void {
    $this->assertSame('', (new Table('orders'))->render($this->theme()));
  }

  public function testThemeWithoutGridThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot draw a table');

    (new Table('orders'))->render(new BlankTheme());
  }

  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]);
  }

}
