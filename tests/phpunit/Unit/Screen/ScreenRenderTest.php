<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Screen\AbstractLayout;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\DefaultLayout;
use DrevOps\Tui\Screen\Screen;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Screen\TwoColumnLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests drawing: the layout sizes, the region flows, the block renders.
 */
#[CoversClass(ScreenRenderer::class)]
#[Group('screen')]
final class ScreenRenderTest extends TestCase {

  public function testEachRegionDrawsItsBlocksInOrder(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('header')->add(new Breadcrumb('Orchard', 'Delivery'));
    $screen->in('content')->add(new Markup('intro', 'Pick the produce.'));
    $screen->in('footer')->add((new Legend())->entry('↵', 'accept'));

    $lines = $this->render($screen, 6, 40);

    $this->assertSame('Orchard › Delivery', $lines[0]);
    $this->assertSame('Pick the produce.', $lines[1]);
    $this->assertSame('↵ to accept', $lines[5]);
  }

  public function testRegionIsGivenExactlyTheRowsItsLayoutAllowed(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add(new Markup('long', implode("\n", array_fill(0, 20, 'row'))));

    // A header and footer of one row each leave four for the content.
    $lines = $this->render($screen, 6, 40);

    $this->assertCount(6, $lines);
    $this->assertSame(['', 'row', 'row', 'row', 'row', ''], $lines);
  }

  public function testPinnedRegionClipsWhereScrollingOneWouldNot(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('header')->add(new Markup('over', "one\ntwo\nthree"));

    $lines = $this->render($screen, 6, 40);

    // The header is one row, so only the first line of three survives.
    $this->assertSame('one', $lines[0]);
    $this->assertSame('', $lines[1]);
  }

  public function testScrollingRegionShowsLaterWindowOnceScrolled(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add(new Markup('rows', "one\ntwo\nthree\nfour\nfive\nsix"));

    $screen->in('content')->scrollTo(2);
    $lines = $this->render($screen, 6, 40);

    // Four rows of content, starting two in.
    $this->assertSame(['', 'three', 'four', 'five', 'six', ''], $lines);
  }

  public function testPinnedRegionCannotBeScrolled(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Region "header" does not scroll, so it cannot be scrolled to row 2.');

    (new DefaultLayout())->in('header')->scrollTo(2);
  }

  public function testScrollingStopsAtTheLastRowRatherThanRunningPastIt(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add(new Markup('rows', "one\ntwo\nthree\nfour\nfive\nsix"));

    $screen->in('content')->scrollTo(99);

    // Six rows into four leaves two: the window stops there rather than
    // scrolling the content off the top of itself.
    $this->assertSame(['', 'three', 'four', 'five', 'six', ''], $this->render($screen, 6, 40));
  }

  public function testBlocksInOneRegionStackDownItByDefault(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')
      ->add(new Markup('first', 'First.'))
      ->add(new Markup('second', 'Second.'));

    $lines = $this->render($screen, 6, 40);

    $this->assertSame('First.', $lines[1]);
    $this->assertSame('Second.', $lines[2]);
  }

  public function testBlocksRunAcrossRegionThatFlowsThatWay(): void {
    $layout = new DefaultLayout();
    $layout->in('header')->flow(Axis::Columns);

    $screen = (new Screen())->layout($layout);
    $screen->in('header')
      ->add(new Breadcrumb('Orchard'))
      ->add(new Markup('clock', '09:14'));

    $lines = $this->render($screen, 6, 40);

    // Side by side, without nesting a layout to do it.
    $this->assertSame('Orchard 09:14', $lines[0]);
  }

  public function testColumnsDrawSideBySideOnTheSameRows(): void {
    $screen = (new Screen())->layout(new TwoColumnLayout());
    $screen->in('left')->add(new Markup('l', "left one\nleft two"));
    $screen->in('right')->add(new Markup('r', 'right one'));

    $lines = $this->render($screen, 2, 24);

    $this->assertSame('left one    right one', rtrim($lines[0]));
    $this->assertSame('left two', rtrim($lines[1]));
  }

  public function testAnEnteredPanelDrawsItsOwnLayoutIntoTheRegion(): void {
    $inner = (new Panel('main', 'Delivery'))->layout(new TwoColumnLayout())->enter();
    $inner->in('left')->add(new Markup('l', 'left'));
    $inner->in('right')->add(new Markup('r', 'right'));

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add($inner);

    $lines = $this->render($screen, 4, 20);

    // The panel is where the second layout starts, which is where depth comes
    // from rather than a fifth level.
    $this->assertSame('left      right', $lines[1]);
  }

  public function testNestedPanelDrawsRowYouSelect(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add($child);

    $this->assertSame('Advanced', $this->render($screen, 4, 20)[1]);
  }

  public function testAnEmptyLayoutDrawsNothingAtAll(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);
      }

    };

    $this->assertSame([], $this->render((new Screen())->layout($layout), 6, 40));
  }

  /**
   * Draw a screen and return its rows.
   *
   * @param \DrevOps\Tui\Screen\Screen $screen
   *   The screen.
   * @param int $rows
   *   The terminal rows.
   * @param int $columns
   *   The terminal columns.
   *
   * @return list<string>
   *   The rows.
   */
  protected function render(Screen $screen, int $rows, int $columns): array {
    $rendered = (new ScreenRenderer(new DefaultTheme($columns, ['color' => FALSE])))->render($screen, $rows, $columns);

    return $rendered === '' ? [] : array_map(rtrim(...), explode("\n", $rendered));
  }

}
