<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen\Layout;

use DrevOps\Tui\Model\FormException;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\GridLayout;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the grid: windows dealt into visual rows, sharing the width.
 */
#[CoversClass(GridLayout::class)]
#[Group('screen')]
final class GridLayoutTest extends TestCase {

  public function testGridDealsTheVisualRowsItWasDeclaredWith(): void {
    $this->assertSame([1, 2], (new GridLayout(1, 2))->deal());
  }

  public function testGridDeclaringNoRowRunsWhatItHoldsOneUnderAnother(): void {
    // Which is what every other arrangement does, so a grid of no rows is the
    // list a panel gets without asking for anything.
    $this->assertSame([], (new GridLayout())->deal());
    $this->assertSame([], (new PanelLayout())->deal());
  }

  public function testGridIsOneScrollingRegionRatherThanOnePerWindow(): void {
    $layout = new GridLayout(1, 2);

    // A window is as tall as what it holds and the whole grid moves together,
    // which is what a region does with its blocks - so windows are blocks in
    // one region rather than regions the layout would have to size.
    $this->assertSame(Axis::Rows, $layout->axis());
    $this->assertSame(['content'], $layout->names());
    $this->assertTrue($layout->in('content')->isScrolling());
  }

  /**
   * Tests that the windows of a visual row divide the width between them.
   *
   * @param int $available
   *   The cells across the region.
   * @param int $count
   *   How many windows share the row.
   * @param int $expected
   *   The cells each of them takes.
   */
  #[DataProvider('dataProviderWindowsOfRowDivideTheWidthBetweenThem')]
  public function testWindowsOfRowDivideTheWidthBetweenThem(int $available, int $count, int $expected): void {
    $this->assertSame($expected, (new GridLayout(1, 2))->share($available, $count));
  }

  /**
   * Data provider for testWindowsOfRowDivideTheWidthBetweenThem().
   *
   * @return \Iterator<string,array{int,int,int}>
   *   The cells across the region, the windows sharing a row, and the cells
   *   each of them takes.
   */
  public static function dataProviderWindowsOfRowDivideTheWidthBetweenThem(): \Iterator {
    yield 'one window takes the region whole' => [56, 1, 56];
    yield 'two split it, less the gutter between them' => [56, 2, 27];
    yield 'three split it, less both gutters' => [56, 3, 17];
    yield 'the odd cell is left over rather than overflowing' => [57, 2, 27];
    yield 'a frame too small still leaves a window a column' => [4, 3, 1];
  }

  public function testShapeWithRowNothingCouldBeDealtIntoIsRefused(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('Every visual row of a grid holds at least one window.');

    new GridLayout(0, 2);
  }

  public function testSlotsThatDoNotCoverTheWindowsAreRefusedNamingTheOwner(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('The grid of "Market stall" declares 3 slot(s) for 2 window(s).');

    (new GridLayout(1, 2))->assertDeals(2, 'Market stall');
  }

  public function testSlotsCoveringTheWindowsPass(): void {
    $this->expectNotToPerformAssertions();

    (new GridLayout(1, 2))->assertDeals(3, 'Market stall');
  }

  public function testGridDealingNothingAssertsNothing(): void {
    // A panel that never asked for windows has none to be counted against.
    $this->expectNotToPerformAssertions();

    (new GridLayout())->assertDeals(7, 'Market stall');
  }

}
