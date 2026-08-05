<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Screen\Layout\GridLayout;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use DrevOps\Tui\Screen\Layout\TwoColumnLayout;
use DrevOps\Tui\Screen\Screen;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\ThemeInterface;
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
    $screen->in('footer')->add((new Legend())->advertise(KeyMapManager::create()->navigation(), new Hint('accept', Action::Activate)));

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
    // The last of those four carries the mark saying there is more below it.
    $this->assertSame(['', 'row', 'row', 'row', 'row                                    ▼', ''], $lines);
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

    // Four rows of content, starting two in, with the first marked because two
    // rows are now out of sight above it.
    $this->assertSame(['', 'three                                  ▲', 'four', 'five', 'six', ''], $lines);
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
    $this->assertSame(['', 'three                                  ▲', 'four', 'five', 'six', ''], $this->render($screen, 6, 40));
  }

  public function testMarkedRowKeepsTheStylingOfTheRowItWasCutFrom(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')
      ->add((new Field('courier', 'Courier'))->default('Valley Runs of the Long Green Valley'))
      ->add((new Field('weight', 'Weight'))->default('1200'));

    $theme = new DefaultTheme(40, ['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark, 'spacing' => Spacing::Normal]);
    $marked = explode("\n", (new ScreenRenderer($theme))->render($screen, 3, 40))[1];

    // The mark sits at the region's edge, so a row already filling the width is
    // cut to make room for it - and a row at a boundary reads exactly as the
    // same row one line in, which costs it a column rather than its colour.
    $this->assertStringContainsString("\033[32mValley Runs of the Long Gree\033[0m", $marked);
    $this->assertSame(40, Ansi::width($marked));
  }

  public function testPinnedRegionSaysNothingAboutWhatItClipped(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('header')->add(new Markup('over', "one\ntwo\nthree"));

    // Only a region you can move through says there is more, because there is
    // no way to reach what a pinned one dropped.
    $this->assertSame('one', $this->render($screen, 6, 40)[0]);
  }

  public function testFramedScreenIsBoxedAndDrawsItsRegionsInside(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('header')->add(new Breadcrumb('Orchard', 'Delivery'));
    $screen->in('content')->add(new Markup('intro', 'Pick the produce.'));

    $lines = $this->render($screen, 6, 24, Border::Rounded);

    $this->assertCount(6, $lines);
    $this->assertSame('╭──────────────────────╮', $lines[0]);
    $this->assertSame('│ Orchard › Delivery   │', $lines[1]);
    $this->assertSame('│ Pick the produce.    │', $lines[2]);
    $this->assertSame('╰──────────────────────╯', $lines[5]);
  }

  public function testFrameFallsBackToTheGlyphsThatNeedNoUnicode(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add(new Markup('intro', 'Pick the produce.'));

    $theme = new DefaultTheme(20, ['color' => FALSE, 'unicode' => FALSE]);
    $lines = explode("\n", (new ScreenRenderer($theme, Border::Rounded))->render($screen, 5, 20));

    $this->assertSame('+------------------+', $lines[0]);
    $this->assertStringStartsWith('| Pick the produce', $lines[2]);
  }

  public function testThemeThatCannotDrawTheChromeSaysSo(): void {
    // The frame belongs to no block, so the theme is asked for it directly -
    // and a theme that declares none of it cannot draw one.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot draw the window chrome');

    (new ScreenRenderer($this->createStub(ThemeInterface::class), Border::Line))->render((new Screen())->layout(new DefaultLayout()), 5, 20);
  }

  public function testBlocksInOneRegionStackDownItByDefault(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')
      ->add(new Markup('first', 'First.'))
      ->add(new Markup('second', 'Second.'));

    $lines = $this->render($screen, 6, 40);

    // One under the other, in the order they were added, with the air the
    // default spacing asks for between them.
    $this->assertSame('First.', $lines[1]);
    $this->assertSame('', $lines[2]);
    $this->assertSame('Second.', $lines[3]);
  }

  public function testBlocksStackAgainstEachOtherWhereTheThemeAsksForNoAir(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')
      ->add(new Markup('first', 'First.'))
      ->add(new Markup('second', 'Second.'));

    $theme = new DefaultTheme(40, ['color' => FALSE, 'spacing' => Spacing::Normal]);
    $lines = array_map(rtrim(...), explode("\n", (new ScreenRenderer($theme))->render($screen, 6, 40)));

    $this->assertSame('First.', $lines[1]);
    $this->assertSame('Second.', $lines[2]);
  }

  public function testBlockWithNothingToSayCostsNoRowAtAll(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')
      ->add(new Markup('first', 'First.'))
      ->add(new Markup('silent', ''))
      ->add(new Markup('second', 'Second.'));

    $lines = $this->render($screen, 6, 40);

    // The silent block is not there at all, so the air between the two that
    // did draw is the one blank row the spacing asks for rather than three.
    $this->assertSame(['', 'First.', '', 'Second.', '', ''], $lines);
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

  public function testEnteredPanelTakesWhatTheBlocksBesideItLeave(): void {
    $inner = (new Panel('main', 'Delivery'))->layout(new PanelLayout())->enter();
    $inner->in('content')->add(new Markup('l', "one\ntwo"));

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add(new Markup('over', 'above'))->add($inner)->add(new Markup('under', 'below'));
    $screen->in('content')->tail(new Markup('version', 'v1.2.3'));

    $lines = $this->render($screen, 9, 20);

    // The panel takes the rows its own layout comes to and no more, so what
    // was placed after it follows its last row rather than the region's - and
    // what packs from the end still packs from the end.
    $this->assertSame(['', 'above', '', 'one', 'two', '', 'below', 'v1.2.3', ''], array_map(rtrim(...), $lines));
  }

  public function testEnteredPanelTakesTheRegionWholeOnceYouGoDeeper(): void {
    $deeper = (new Panel('advanced', 'Advanced'))->layout(new PanelLayout())->enter();
    $deeper->in('content')->add(new Markup('c', 'certifier'));

    $inner = (new Panel('main', 'Delivery'))->layout(new PanelLayout())->enter();
    $inner->in('content')->add(new Markup('l', 'courier'))->add($deeper);

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add($inner)->add(new Markup('under', 'below'));

    $lines = $this->render($screen, 6, 20);

    // Going deeper replaces the view: the rows beside the panel go with it,
    // wherever they were placed.
    $this->assertSame(['', 'certifier', '', '', '', ''], array_map(rtrim(...), $lines));
  }

  public function testNestedPanelDrawsRowYouSelect(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add($child);

    $this->assertSame('  Advanced ›', $this->render($screen, 4, 20)[1]);
  }

  public function testGridIsMeasuredTheWayItIsDrawn(): void {
    $grid = new GridLayout(2, 1);
    $hub = (new Panel('hub', 'Hub'))->layout($grid)->enter();
    $grid->in($grid->leading())->add(new Markup('intro', 'Pick the produce.'));

    $screen = (new Screen())->layout(new DefaultLayout());
    $screen->in('content')->add($hub);

    $placed = 0;

    foreach (['fruit' => 'Fruit', 'veg' => 'Vegetables', 'dairy' => 'Dairy'] as $id => $title) {
      $window = (new Panel($id, $title))->layout(new DefaultLayout());
      $window->in('content')->add(new Markup($id . '-note', "one\ntwo"));
      $grid->in($grid->windows()[$placed])->add($window);
      $placed++;
    }

    $renderer = new ScreenRenderer(new DefaultTheme(40, ['color' => FALSE]));

    // Every region is measured on its own, and the arrangement is what adds
    // them up: the row above, then two windows sharing three rows, then the
    // window below sharing none - so two windows side by side cost the rows of
    // one rather than of both. Every line but the last carries the row telling
    // it from the next.
    $sizes = $renderer->sizes($grid, 40);
    $this->assertSame(1, $renderer->extent($grid, 'above')[0]);
    $this->assertSame(3, $renderer->extent($grid, 'window-1')[0]);
    $this->assertSame(['above' => 2, 'window-1' => 4, 'window-2' => 4, 'window-3' => 3, 'below' => 0], $sizes);

    // The arrangement is measured as one surface too, which is what it is
    // moved against when it outruns its space.
    $this->assertSame([9, 6], $renderer->reach($grid, $grid->in('window-3')->blocks()[0]));

    // What was counted is what is drawn: the windows come to exactly those
    // rows, so a surface moved against them shows what it says it does.
    $drawn = $this->render($screen, 11, 40);
    $this->assertSame('  Dairy ›', $drawn[7]);
    $this->assertSame('two', $drawn[9]);
  }

  public function testBlockPackedFromTheEndSitsAgainstTheFarEdgeOfTheFlow(): void {
    $layout = new DefaultLayout();
    $layout->in('footer')->flow(Axis::Columns);

    $screen = (new Screen())->layout($layout);
    $screen->in('footer')->add((new Legend())->advertise(KeyMapManager::create()->navigation(), new Hint('accept', Action::Activate)))->tail(new Markup('version', 'v1.2.3'));

    // A footer flows its key hints from one end and a version string from the
    // other, without a second arrangement to put them there.
    $this->assertSame('↵ to accept                       v1.2.3', $this->render($screen, 6, 40)[5]);
  }

  public function testBlockPackedFromTheEndSitsOnTheLastRowWhereTheFlowRunsDown(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('content');
      }

    };

    $screen = (new Screen())->layout($layout);
    $screen->in('content')->add(new Markup('intro', 'Pick the produce.'))->tail(new Markup('version', 'v1.2.3'));

    // The same statement with the axis turned: the end of a flow running down
    // a region is its last row.
    $this->assertSame(['Pick the produce.', '', '', '', '', 'v1.2.3'], $this->render($screen, 6, 40));
  }

  public function testWindowPackedFromTheEndIsDrawnTheWayItWasMeasured(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('content')->content()->previews();
      }

    };

    $window = (new Panel('fruit', 'Fruit'))->layout(new DefaultLayout());
    $window->in('content')->add(new Markup('note', "apples\npears"));

    $screen = (new Screen())->layout($layout);
    $screen->in('content')->tail($window);

    $renderer = new ScreenRenderer(new DefaultTheme(40, ['color' => FALSE]));

    // A window shows what is behind it whichever end of the flow it was packed
    // from, so the rows it is counted at are the rows it draws.
    $this->assertSame(3, $renderer->extent($layout, 'content')[0]);
    $this->assertSame(['  Fruit ›', 'apples', 'pears'], $this->render($screen, 3, 40));
  }

  public function testLineIsAsDeepAsTheDeepestRegionDrawnOnIt(): void {
    $layout = new class() extends AbstractLayout {

      public function __construct() {
        parent::__construct(Axis::Rows);

        $this->region('left')->content();
        $this->region('right')->content();
      }

      #[\Override]
      public function lines(): array {
        return [['left', 'right']];
      }

      #[\Override]
      public function arrange(int $available, array $measured = []): array {
        return ['left' => 1, 'right' => 3];
      }

      #[\Override]
      public function share(int $available, int $count, ChromeElementsInterface $chrome): int {
        return intdiv($available, max(1, $count));
      }

    };

    $screen = (new Screen())->layout($layout);
    $screen->in('left')->add(new Markup('one', 'pears'));
    $screen->in('right')->add(new Markup('two', "apples\ncherries\nplums"));

    // An arrangement of a consumer's own can give the regions of a line sizes
    // of their own, and the line has to reach the deepest of them rather than
    // however deep whichever of them is named first happens to be.
    $this->assertSame(['pears                 apples', '                      cherries', '                      plums'], $this->render($screen, 3, 40));
  }

  public function testWhereTheTwoEndsMeetTheHeadKeepsItsSpaceAndTheTailIsCut(): void {
    $layout = new DefaultLayout();
    $layout->in('header')->flow(Axis::Columns);

    $screen = (new Screen())->layout($layout);
    $screen->in('header')->add(new Markup('trail', 'Orchard › Delivery › Certification'))->tail(new Markup('version', 'v1.2.3'));
    $screen->in('footer')->add(new Markup('legend', 'Legend'))->tail(new Markup('build', 'built today'));

    $lines = $this->render($screen, 6, 40);

    // Whichever axis they meet on, what packs from the start keeps every cell
    // it drew and what packs from the end loses what will not fit: across the
    // header the version string is cut, and down a one-row footer there is no
    // row left for what was packed at its end.
    $this->assertSame('Orchard › Delivery › Certification v1.2.', $lines[0]);
    $this->assertSame('Legend', $lines[5]);
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
   * @param \DrevOps\Tui\Theme\Border $border
   *   The frame drawn around every region at once.
   *
   * @return list<string>
   *   The rows.
   */
  protected function render(Screen $screen, int $rows, int $columns, Border $border = Border::None): array {
    $rendered = (new ScreenRenderer(new DefaultTheme($columns, ['color' => FALSE]), $border))->render($screen, $rows, $columns);

    return $rendered === '' ? [] : array_map(rtrim(...), explode("\n", $rendered));
  }

}
