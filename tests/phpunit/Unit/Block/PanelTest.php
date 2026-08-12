<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Block;

use DrevOps\PhpTui\Block\Buttons;
use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Screen\Layout\DefaultLayout;
use DrevOps\PhpTui\Screen\Layout\GridLayout;
use DrevOps\PhpTui\Screen\Layout\PanelLayout;
use DrevOps\PhpTui\Screen\Layout\TwoColumnLayout;
use DrevOps\PhpTui\Theme\DefaultTheme;
use DrevOps\PhpTui\Theme\Spacing;
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

  public function testNestedPanelDrawsTheWayInAsRowYouSelect(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout());
    $theme = new DefaultTheme(40, ['color' => FALSE]);

    // The mark saying where the cursor is, what the panel is called, and the
    // mark saying the row leads somewhere rather than opening in place.
    $this->assertSame('  Advanced ›', $child->render($theme));
    $this->assertSame('❯ Advanced ›', $child->focus()->render($theme));
  }

  public function testNestedPanelSaysWhatItIsHoldingUnderItsRow(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout())->description('What the packers need.');
    $child->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));
    $child->in('content')->add((new Field('grade', 'Grade'))->default('Premium'));

    $theme = new DefaultTheme(40, ['color' => FALSE]);

    $this->assertSame([
      '  Advanced ›',
      '    What the packers need.',
      '    Valley Runs · Premium',
    ], explode("\n", $child->render($theme)));
  }

  public function testNestedPanelDrawnAsWindowShowsTheRowsThemselves(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout())->description('What the packers need.');
    $child->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    $theme = new DefaultTheme(40, ['color' => FALSE]);

    // Where the row says what is behind it in one line, the window shows the
    // rows behind it instead.
    $this->assertSame([
      '  Advanced ›',
      '    What the packers need.',
      '  Courier  Valley Runs',
    ], explode("\n", $child->preview($theme)));
  }

  public function testCompactPanelRowDropsEverythingButTheWayIn(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout())->description('What the packers need.');
    $child->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    $theme = new DefaultTheme(40, ['color' => FALSE, 'spacing' => Spacing::Compact]);

    $this->assertSame('  Advanced ›', $child->render($theme));
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

  public function testPanelHandsBackWhatItStillHasToPrepare(): void {
    $work = static function (): void {
    };
    $panel = (new Panel('delivery', 'Delivery'))->preload($work);

    $this->assertSame($work, $panel->preparation());
    // Doing it is what leaves nothing to do, so it is gone once it has run.
    $panel->prepare();
    $this->assertNull($panel->preparation());
  }

  public function testPanelReadsHowItIsMovedThroughOffTheArrangement(): void {
    $listed = (new Panel('delivery', 'Delivery'))->layout(new PanelLayout());
    $dealt = (new Panel('delivery', 'Delivery'))->layout(new GridLayout(1, 2));

    // A panel arranges nothing itself, so which keys move through it is read
    // off the layout it nests rather than off anything it was told.
    $this->assertSame('move', $listed->hints()[0]->label);
    $this->assertSame([Action::MoveUp, Action::MoveDown], $listed->hints()[0]->actions);
    $this->assertSame([Action::MoveUp, Action::MoveDown, Action::MoveLeft, Action::MoveRight], $dealt->hints()[0]->actions);
  }

  public function testWayIntoPanelIsTheLineThatSaysSoAndNothingMore(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new DefaultLayout())->description('What the packers need.');
    $child->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    // The full row belongs to a list, where there is a width to spend on it.
    $this->assertSame('  Advanced ›', $child->wayIn(new DefaultTheme(40, ['color' => FALSE])));
  }

  public function testWindowDrawsWayIntoPanelItHoldsRatherThanItsWholeRow(): void {
    $child = (new Panel('advanced', 'Advanced'))->layout(new PanelLayout())->description('What the packers need.');
    $child->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    $window = (new Panel('delivery', 'Delivery'))->layout(new PanelLayout());
    $window->in('content')->add($child);

    // A window is a column previewing one panel, so a way into another is one
    // line of it rather than a description and a summary as well.
    $this->assertSame(['  Delivery ›', '  Advanced ›'], explode("\n", $window->preview(new DefaultTheme(40, ['color' => FALSE]))));
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
