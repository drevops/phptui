<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Screen\Layout\TwoColumnLayout;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the panel: the block that nests a layout and can be entered.
 */
#[CoversClass(Panel::class)]
#[Group('block')]
final class PanelTest extends TestCase {

  public function testPanelNestsLayoutRatherThanHoldingBlocks(): void {
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

  public function testPanelWithoutLayoutHasNowhereToPutBlock(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Panel "delivery" has no layout, so it has no regions to place a block in.');

    (new Panel('delivery', 'Delivery'))->in('left');
  }

  public function testNestedPanelDrawsItsTitleAsRowYouSelect(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    $this->assertSame('Advanced', $child->render($theme));
  }

  public function testPanelYouAreInDrawsNoRowOfItsOwn(): void {
    $panel = (new Panel('delivery', 'Delivery'))->layout(new DefaultLayout());

    $this->assertFalse($panel->isEntered());
    $this->assertTrue($panel->enter()->isEntered());

    // Its blocks draw instead, so asking it to draw a row is a mistake worth
    // catching rather than a title nobody asked for.
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Panel "delivery" is entered, so its blocks draw rather than the panel itself.');

    $panel->render(new DefaultTheme(40, ['color' => FALSE]));
  }

  public function testPanelCarriesItsTitleIntoTheTrail(): void {
    $panel = new Panel('delivery', 'Delivery');

    $this->assertSame('delivery', $panel->id());
    $this->assertSame('Delivery', $panel->title());
  }

  public function testModalIsTheSamePanelDrawnOverWhatIsBehindIt(): void {
    $panel = new Panel('confirm', 'Confirm delivery');

    $this->assertFalse($panel->isModal());
    $this->assertTrue($panel->modal()->isModal());
  }

  public function testPanelCarriesTheStandingTextUnderItsTitle(): void {
    $panel = new Panel('delivery', 'Delivery');

    $this->assertSame('', $panel->descriptionText());
    $this->assertSame('Every crate leaves at dawn.', $panel->description('Every crate leaves at dawn.')->descriptionText());
  }

  public function testPanelLabelsTheWayOutOfIt(): void {
    $panel = new Panel('confirm', 'Confirm delivery');
    $buttons = new Buttons(TRUE, 'Send', 'Keep packing');

    $this->assertSame('Submit', $panel->currentButtons()->submitLabel);
    $this->assertSame('Keep packing', $panel->buttons($buttons)->currentButtons()->cancelLabel);
  }

  #[DataProvider('dataProviderPanelDrawnOverEverythingCannotHideItsOnlyWayOut')]
  public function testPanelDrawnOverEverythingCannotHideItsOnlyWayOut(\Closure $declare): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Panel "confirm" draws over what is behind it, so its buttons are its only way out and cannot be hidden.');

    $declare(new Panel('confirm', 'Confirm delivery'));
  }

  public static function dataProviderPanelDrawnOverEverythingCannotHideItsOnlyWayOut(): \Iterator {
    yield 'hidden after it overlays' => [static fn(Panel $panel): Panel => $panel->modal()->buttons(new Buttons(FALSE))];
    yield 'overlaid after they are hidden' => [static fn(Panel $panel): Panel => $panel->buttons(new Buttons(FALSE))->modal()];
  }

  public function testPanelPreparesWhatItNeedsOnceBeforeItIsFirstEntered(): void {
    $prepared = 0;
    $panel = (new Panel('delivery', 'Delivery'))->preload(static function () use (&$prepared): void {
      $prepared++;
    });

    $this->assertTrue($panel->prepare());
    // Preparation happens once, so a panel entered again does not redo it.
    $this->assertFalse($panel->prepare());
    $this->assertSame(1, $prepared);
  }

  public function testPanelWithNothingToPrepareHasNothingToDo(): void {
    $this->assertFalse((new Panel('delivery', 'Delivery'))->prepare());
  }

  public function testPanelHoldsTheSubPanelsYouCanDescendInto(): void {
    $parent = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());
    $child = new Panel('advanced', 'Advanced');

    $parent->in('content')->add($child);

    $this->assertSame([$child], $parent->children());
  }

  public function testPanelWithNoLayoutHoldsNothingToDescendInto(): void {
    $this->assertSame([], (new Panel('main', 'Delivery'))->children());
  }

  public function testBlockThatIsNotPanelIsNoDestination(): void {
    $parent = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());
    $parent->in('content')->add(new Markup('intro', 'Pick the produce.'));

    $this->assertSame([], $parent->children());
  }

}
