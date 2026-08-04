<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen\Layout;

use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Screen\Layout\TwoColumnLayout;
use DrevOps\Tui\Screen\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the layout: it declares regions and works out how big each one is.
 */
#[CoversClass(AbstractLayout::class)]
#[CoversClass(DefaultLayout::class)]
#[CoversClass(TwoColumnLayout::class)]
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

  public function testTheTwoColumnLayoutSplitsLeftFromRight(): void {
    $layout = new TwoColumnLayout();

    $this->assertSame(Axis::Columns, $layout->axis());
    $this->assertSame(['left', 'right'], $layout->names());
  }

  public function testArrangementDealingNothingGivesOneBlockTheRegionWhole(): void {
    $layout = new DefaultLayout();

    // A row is one block where nothing is dealt, so it takes the region as it
    // stands however many blocks are stacked under it.
    $this->assertSame([], $layout->deal());
    $this->assertSame(40, $layout->share(40, 1));
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
