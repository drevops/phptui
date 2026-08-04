<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen\Layout;

use DrevOps\Tui\Model\FormException;
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
    // being counted into place by something looking past its own level.
    $this->assertSame(Axis::Rows, $layout->axis());
    $this->assertSame(['content', 'window-1', 'window-2', 'window-3'], $layout->names());
    $this->assertSame(['window-1', 'window-2', 'window-3'], $layout->windows());
  }

  public function testWindowIsAsDeepAsWhatItHoldsAndShowsWhatIsBehindIt(): void {
    $layout = new GridLayout(2);

    foreach (['content', 'window-1', 'window-2'] as $name) {
      $this->assertSame(Sizing::Content, $layout->in($name)->sizing());
      $this->assertTrue($layout->in($name)->isScrolling());
    }

    // The panel's own rows are a list; only the windows are windows.
    $this->assertFalse($layout->in('content')->isPreviewing());
    $this->assertTrue($layout->in('window-1')->isPreviewing());
    $this->assertTrue($layout->in('window-2')->isPreviewing());
  }

  public function testVisualRowsAreTheLinesTheRegionsAreDrawnOn(): void {
    // The panel's own rows have the first line to themselves, which is what
    // puts them above the grid rather than in it.
    $this->assertSame([['content'], ['window-1'], ['window-2', 'window-3']], (new GridLayout(1, 2))->lines());
  }

  public function testWindowsSharingOneLineAreAsDeepAsTheDeepestOfThem(): void {
    $layout = new GridLayout(1, 2);
    $sizes = $layout->arrange(40, ['content' => 1, 'window-1' => 2, 'window-2' => 3, 'window-3' => 5]);

    // Two windows beside each other cost the rows of one rather than of both,
    // and every line but the last carries the row telling it from the next.
    $this->assertSame(['content' => 2, 'window-1' => 3, 'window-2' => 5, 'window-3' => 5], $sizes);
    $this->assertSame(10, $layout->natural(['content' => 1, 'window-1' => 2, 'window-2' => 3, 'window-3' => 5]));
  }

  public function testLineHoldingNothingCostsNoRowAtAll(): void {
    $layout = new GridLayout(2);

    // A grid whose panel declares no rows of its own opens on its windows: the
    // empty line takes no row, and none is spent telling it from the next.
    $this->assertSame(['content' => 0, 'window-1' => 4, 'window-2' => 4], $layout->arrange(40, ['window-1' => 4, 'window-2' => 2]));
    $this->assertSame(4, $layout->natural(['window-1' => 4, 'window-2' => 2]));
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
