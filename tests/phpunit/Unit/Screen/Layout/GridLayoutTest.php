<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen\Layout;

use DrevOps\Tui\FormException;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\GridLayout;
use DrevOps\Tui\Screen\Sizing;
use DrevOps\Tui\Theme\DefaultTheme;
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

  public function testGridDeclaresOneRegionPerWindowItDealsInto(): void {
    $layout = new GridLayout(1, 2);

    // A window is a region, so a panel reaches one by naming it rather than by
    // being counted into place by something looking past its own level - and
    // the rows written either side of the grid have a region each.
    $this->assertSame(Axis::Rows, $layout->axis());
    $this->assertSame(['above', 'window-1', 'window-2', 'window-3', 'below'], $layout->names());
    $this->assertSame(['window-1', 'window-2', 'window-3'], $layout->windows());
    $this->assertSame('above', $layout->leading());
    $this->assertSame('below', $layout->trailing());
  }

  public function testWindowIsAsDeepAsWhatItHoldsAndShowsWhatIsBehindIt(): void {
    $layout = new GridLayout(2);

    foreach (['above', 'window-1', 'window-2', 'below'] as $name) {
      $this->assertSame(Sizing::Content, $layout->in($name)->sizing());
      // The grid moves every line together, so no region moves on its own.
      $this->assertFalse($layout->in($name)->isScrolling());
    }

    // The panel's own rows are a list; only the windows are windows.
    $this->assertFalse($layout->in('above')->isPreviewing());
    $this->assertFalse($layout->in('below')->isPreviewing());
    $this->assertTrue($layout->in('window-1')->isPreviewing());
    $this->assertTrue($layout->in('window-2')->isPreviewing());
  }

  public function testGridMovesEveryLineTogetherAsOneSurface(): void {
    $layout = new GridLayout(1, 2);

    // No window can move its siblings, so the arrangement is the surface - and
    // it is moved the way any surface is.
    $this->assertTrue($layout->isScrolling());
    $this->assertSame(3, $layout->scrollTo(3)->offset(20, 8));
    // Never far enough to scroll the lines off their own end.
    $this->assertSame(2, $layout->scrollTo(9)->offset(10, 8));
  }

  public function testVisualRowsAreTheLinesTheRegionsAreDrawnOn(): void {
    // The rows written either side have a line to themselves, which is what
    // keeps them out of the grid rather than in it.
    $this->assertSame([['above'], ['window-1'], ['window-2', 'window-3'], ['below']], (new GridLayout(1, 2))->lines());
  }

  public function testWindowsSharingOneLineAreAsDeepAsTheDeepestOfThem(): void {
    $measured = ['above' => 1, 'window-1' => 2, 'window-2' => 3, 'window-3' => 5, 'below' => 1];
    $layout = new GridLayout(1, 2);

    // Two windows beside each other cost the rows of one rather than of both,
    // and every line but the last carries the row telling it from the next.
    $this->assertSame(['above' => 2, 'window-1' => 3, 'window-2' => 6, 'window-3' => 6, 'below' => 1], $layout->arrange(40, $measured));
    $this->assertSame(12, $layout->natural($measured));
  }

  public function testLineHoldingNothingCostsNoRowAtAll(): void {
    $layout = new GridLayout(2);

    // A grid whose panel declares no rows of its own opens on its windows: the
    // empty lines take no row, and none is spent telling them from the next.
    $this->assertSame(['above' => 0, 'window-1' => 4, 'window-2' => 4, 'below' => 0], $layout->arrange(40, ['window-1' => 4, 'window-2' => 2]));
    $this->assertSame(4, $layout->natural(['window-1' => 4, 'window-2' => 2]));
  }

  public function testRowTheGridCannotBeToldApartOnStillGoesToWindow(): void {
    $layout = new GridLayout(1, 1);
    $measured = ['above' => 0, 'window-1' => 1, 'window-2' => 1, 'below' => 0];

    // The row telling one visual row from the next is owed only where there is
    // a second row to tell apart, so a terminal with a single row to give hands
    // it to the first window rather than to the air above a window it has no
    // room to draw.
    $this->assertSame(['above' => 0, 'window-1' => 1, 'window-2' => 0, 'below' => 0], $layout->arrange(1, $measured));
    $this->assertSame(['above' => 0, 'window-1' => 1, 'window-2' => 0, 'below' => 0], $layout->arrange(2, $measured));
    $this->assertSame(['above' => 0, 'window-1' => 2, 'window-2' => 1, 'below' => 0], $layout->arrange(3, $measured));
  }

  /**
   * Tests that the windows of a visual row divide the width between them.
   *
   * @param int $available
   *   The cells across the line.
   * @param int $count
   *   How many windows share the row.
   * @param int $expected
   *   The cells each of them takes.
   */
  #[DataProvider('dataProviderWindowsOfRowDivideTheWidthBetweenThem')]
  public function testWindowsOfRowDivideTheWidthBetweenThem(int $available, int $count, int $expected): void {
    $this->assertSame($expected, (new GridLayout(1, 2))->share($available, $count, new DefaultTheme()));
  }

  /**
   * Data provider for testWindowsOfRowDivideTheWidthBetweenThem().
   *
   * @return \Iterator<string,array{int,int,int}>
   *   The cells across the line, the windows sharing a row, and the cells each
   *   of them takes.
   */
  public static function dataProviderWindowsOfRowDivideTheWidthBetweenThem(): \Iterator {
    yield 'one window takes the line whole' => [56, 1, 56];
    yield 'two split it, less the gutter between them' => [56, 2, 27];
    yield 'three split it, less both gutters' => [56, 3, 17];
    yield 'the odd cell is left over rather than overflowing' => [57, 2, 27];
    yield 'a frame too small still leaves a window a column' => [4, 3, 1];
  }

  public function testGutterComesOffTheWidthAsTheThemeDrawsIt(): void {
    $bare = new class(0, []) extends DefaultTheme {

      #[\Override]
      public function chromeGutter(): int {
        return 0;
      }

    };

    // The air between two windows is one fact, so what the arrangement takes
    // off the width is what the renderer will leave there.
    $this->assertSame(28, (new GridLayout(2))->share(56, 2, $bare));
  }

  public function testShapeWithNoVisualRowIsRefused(): void {
    $this->expectException(FormException::class);
    $this->expectExceptionMessage('A grid is a shape, so it is declared with the windows of at least one visual row.');

    new GridLayout();
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

}
