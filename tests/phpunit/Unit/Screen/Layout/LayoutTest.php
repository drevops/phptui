<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen\Layout;

use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\Capability\ScrollCapableTrait;
use DrevOps\PhpTui\Screen\Furniture;
use DrevOps\PhpTui\Screen\Layout\AbstractLayout;
use DrevOps\PhpTui\Screen\Layout\DefaultLayout;
use DrevOps\PhpTui\Screen\Layout\TwoColumnLayout;
use DrevOps\PhpTui\Screen\Region;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the layout: it declares regions and works out how big each one is.
 */
#[CoversClass(AbstractLayout::class)]
#[CoversClass(DefaultLayout::class)]
#[CoversClass(TwoColumnLayout::class)]
#[CoversTrait(ScrollCapableTrait::class)]
#[Group('screen')]
final class LayoutTest extends TestCase {

  public function testRegionsAreReachedByName(): void {
    $layout = new DefaultLayout();

    $this->assertInstanceOf(Region::class, $layout->in('content'));
    $this->assertSame('content', $layout->in('content')->name());
  }

  public function testReachingForRegionThatWasNeverDeclaredSaysWhichExist(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown region "sidebar". This layout declares: header, content, footer.');

    (new DefaultLayout())->in('sidebar');
  }

  public function testFixedRegionsComeOffTheTopAndTheRestShareWhatIsLeft(): void {
    $layout = new DefaultLayout();

    // 24 rows, one to the header and one to the footer, 22 to content.
    $this->assertSame(['header' => 1, 'content' => 22, 'footer' => 1], $layout->arrange(24));
  }

  public function testSharesDivideTheRemainderInProportion(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('top')->flex(30);
        $this->region('middle')->flex(40);
        $this->region('bottom')->flex(30);
      }

    };

    $this->assertSame(['top' => 30, 'middle' => 40, 'bottom' => 30], $layout->arrange(100));
  }

  public function testThirtyFortyThirtyAndThreeFourThreeMeanTheSameThing(): void {
    $percent = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
        $this->region('a')->flex(30);
        $this->region('b')->flex(40);
        $this->region('c')->flex(30);
      }

    };

    $ratio = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
        $this->region('a')->flex(3);
        $this->region('b')->flex(4);
        $this->region('c')->flex(3);
      }

    };

    $this->assertSame($percent->arrange(37), $ratio->arrange(37));
  }

  public function testTheLeftoverCellGoesToTheLastRegionTakingShare(): void {
    $layout = new TwoColumnLayout();

    // 81 does not halve, so one column carries the odd cell rather than the
    // frame losing it.
    $sizes = $layout->arrange(81);
    $this->assertSame(81, array_sum($sizes));
    $this->assertSame(['left' => 40, 'right' => 41], $sizes);
  }

  public function testFixedRegionKeepsItsSizeHoweverLargeTheTerminal(): void {
    $layout = new DefaultLayout();

    $this->assertSame(1, $layout->arrange(24)['header']);
    $this->assertSame(1, $layout->arrange(120)['header']);
  }

  public function testFixedRegionsAreTrimmedWhenTheyCannotAllFit(): void {
    $layout = new DefaultLayout();

    // Two rows cannot hold a header, a footer and any content at all: the
    // fixed regions are cut back rather than the layout returning sizes that
    // add up to more than there is.
    $sizes = $layout->arrange(2);

    $this->assertSame(2, array_sum($sizes));
    $this->assertSame(0, $sizes['content']);

    // One row cannot hold both of them either, so they are trimmed in
    // declaration order and the header is the one that keeps its row.
    $this->assertSame(['header' => 1, 'content' => 0, 'footer' => 0], $layout->arrange(1));
  }

  public function testLayoutWithNoRegionsArrangesNothing(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
      }

    };

    $this->assertSame([], $layout->arrange(40));
  }

  public function testDeclaringTheSameRegionTwiceIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Region "header" is already declared on this layout.');

    new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
        $this->region('header');
        $this->region('header');
      }

    };
  }

  public function testTheDefaultLayoutStacksThreeRegionsAndScrollsTheMiddle(): void {
    $layout = new DefaultLayout();

    $this->assertSame(Axis::Rows, $layout->axis());
    $this->assertSame(['header', 'content', 'footer'], $layout->names());
    $this->assertFalse($layout->in('header')->isScrolling());
    $this->assertTrue($layout->in('content')->isScrolling());
    $this->assertFalse($layout->in('footer')->isScrolling());
  }

  public function testArrangementStacksItsRegionsRatherThanMovingThemAsOne(): void {
    // Only an arrangement whose lines have to move together is a surface; the
    // rest hand each region a size and leave the moving to it.
    $this->assertFalse((new DefaultLayout())->isScrolling());

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage(DefaultLayout::class . ' does not scroll, so it cannot be scrolled to row 2.');

    (new DefaultLayout())->scrollTo(2);
  }

  public function testTheTwoColumnLayoutSplitsLeftFromRight(): void {
    $layout = new TwoColumnLayout();

    $this->assertSame(Axis::Columns, $layout->axis());
    $this->assertSame(['left', 'right'], $layout->names());
  }

  public function testStackedRegionsEachTakeTheirOwnLineAndItsWidth(): void {
    $layout = new DefaultLayout();

    // One region to a line is what stacking regions means, so each of them has
    // the width to itself however wide the frame is.
    $this->assertSame([['header'], ['content'], ['footer']], $layout->lines());
    $this->assertSame(40, $layout->share(40, 1, new DefaultTheme()));
  }

  public function testRegionsRunAgainstEachOtherUnlessAnArrangementSaysOtherwise(): void {
    $layout = new DefaultLayout();

    // Nothing is spent telling one region from the next, which is what puts a
    // header on the row directly above the rows it introduces.
    $this->assertSame(24, array_sum($layout->arrange(24)));
    $this->assertSame(6, $layout->natural(['header' => 1, 'content' => 4, 'footer' => 1]));
  }

  public function testMeasuredRegionTakesWhatItHoldsAndNoMore(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('note')->content();
        $this->region('rows')->flex(1);
      }

    };

    // What it holds is counted where blocks are drawn and handed over as a
    // number, so the region states a kind and the layout does the arithmetic.
    $this->assertSame(['note' => 3, 'rows' => 21], $layout->arrange(24, ['note' => 3, 'rows' => 40]));
    $this->assertSame(43, $layout->natural(['note' => 3, 'rows' => 40]));
  }

  #[DataProvider('dataProviderLayoutSaysWhereEachPieceOfFurnitureGoes')]
  public function testLayoutSaysWhereEachPieceOfFurnitureGoes(Furniture $piece, ?string $default, ?string $columns): void {
    $this->assertSame($default, (new DefaultLayout())->furnishes($piece));
    $this->assertSame($columns, (new TwoColumnLayout())->furnishes($piece));
  }

  /**
   * Data provider for testLayoutSaysWhereEachPieceOfFurnitureGoes().
   *
   * @return \Iterator<string, array{\DrevOps\PhpTui\Screen\Furniture, string|null, string|null}>
   *   The piece, the region the default layout keeps for it, and the region
   *   the two-column layout keeps for it.
   */
  public static function dataProviderLayoutSaysWhereEachPieceOfFurnitureGoes(): \Iterator {
    // The conventional names, and what a layout using none of them answers: it
    // has nowhere for the trail or the keys, and the form goes in the region
    // it declared first, because a form has to be drawn somewhere.
    yield 'the trail' => [Furniture::Trail, 'header', NULL];
    yield 'the form' => [Furniture::Body, 'content', 'left'];
    yield 'the keys' => [Furniture::Keys, 'footer', NULL];
  }

  public function testLayoutWithNoRegionsKeepsNoPlaceForTheFormEither(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
      }

    };

    $this->assertNull($layout->furnishes(Furniture::Body));
  }

  public function testLayoutNamesNoBlockItMightHold(): void {
    // Reuse is what a layout would lose by knowing its content: it declares
    // arrangement and nothing else, so its regions arrive empty.
    foreach ([new DefaultLayout(), new TwoColumnLayout()] as $layout) {
      foreach ($layout->names() as $name) {
        $this->assertSame([], $layout->in($name)->blocks());
      }
    }
  }

}
