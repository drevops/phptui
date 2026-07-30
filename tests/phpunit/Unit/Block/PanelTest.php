<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Screen\DefaultLayout;
use DrevOps\Tui\Screen\TwoColumnLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the panel: the block that nests a layout and can be entered.
 */
#[CoversClass(Panel::class)]
#[Group('block')]
final class PanelTest extends TestCase {

  public function testAPanelNestsALayoutRatherThanHoldingBlocks(): void {
    $layout = new TwoColumnLayout();
    $panel = (new Panel('delivery', 'Delivery'))->layout($layout);

    $this->assertSame($layout, $panel->currentLayout());
  }

  public function testItsBlocksGoIntoItsLayoutsRegions(): void {
    $panel = (new Panel('delivery', 'Delivery'))->layout(new TwoColumnLayout());
    $courier = new Markup('courier', 'Valley Runs');

    $panel->in('left')->add($courier);

    $this->assertSame([$courier], $panel->currentLayout()->in('left')->blocks());
  }

  public function testAPanelWithoutALayoutHasNowhereToPutABlock(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Panel "delivery" has no layout, so it has no regions to place a block in.');

    (new Panel('delivery', 'Delivery'))->in('left');
  }

  public function testANestedPanelDrawsItsTitleAsARowYouSelect(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    $this->assertSame('Advanced', $child->render($theme));
  }

  public function testAPanelYouAreInDrawsNoRowOfItsOwn(): void {
    $panel = (new Panel('delivery', 'Delivery'))->layout(new DefaultLayout());

    $this->assertFalse($panel->isEntered());
    $this->assertTrue($panel->enter()->isEntered());

    // Its blocks draw instead, so asking it to draw a row is a mistake worth
    // catching rather than a title nobody asked for.
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Panel "delivery" is entered, so its blocks draw rather than the panel itself.');

    $panel->render(new DefaultTheme(40, ['color' => FALSE]));
  }

  public function testAPanelCarriesItsTitleIntoTheTrail(): void {
    $panel = new Panel('delivery', 'Delivery');

    $this->assertSame('delivery', $panel->id());
    $this->assertSame('Delivery', $panel->title());
  }

  public function testAModalIsTheSamePanelDrawnOverWhatIsBehindIt(): void {
    $panel = new Panel('confirm', 'Confirm delivery');

    $this->assertFalse($panel->isModal());
    $this->assertTrue($panel->modal()->isModal());
  }

  public function testAPanelHoldsTheSubPanelsYouCanDescendInto(): void {
    $parent = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());
    $child = new Panel('advanced', 'Advanced');

    $parent->in('content')->add($child);

    $this->assertSame([$child], $parent->children());
  }

  public function testABlockThatIsNotAPanelIsNoDestination(): void {
    $parent = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());
    $parent->in('content')->add(new Markup('intro', 'Pick the produce.'));

    $this->assertSame([], $parent->children());
  }

}
