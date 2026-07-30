<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Screen\AbstractLayout;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\DefaultLayout;
use DrevOps\Tui\Screen\LayoutManager;
use DrevOps\Tui\Screen\Screen;
use DrevOps\Tui\Screen\TwoColumnLayout;
use DrevOps\Tui\Tests\Traits\ResetsRegistriesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the screen root and the layout registry.
 */
#[CoversClass(Screen::class)]
#[CoversClass(LayoutManager::class)]
#[Group('screen')]
final class ScreenTest extends TestCase {

  use ResetsRegistriesTrait;

  protected function setUp(): void {
    LayoutManager::reset();
  }

  protected function tearDown(): void {
    LayoutManager::reset();
  }

  public function testScreenNestsOneLayout(): void {
    $layout = new DefaultLayout();

    $this->assertSame($layout, (new Screen())->layout($layout)->currentLayout());
  }

  public function testScreenFitsItsContentsUntilToldToOccupyTheTerminal(): void {
    $screen = (new Screen())->layout(new DefaultLayout());

    $this->assertFalse($screen->isFullscreen());
    $this->assertTrue($screen->fullscreen()->isFullscreen());
  }

  public function testBlockGoesInByRegionName(): void {
    $screen = (new Screen())->layout(new DefaultLayout());
    $breadcrumb = new Breadcrumb('Orchard');

    $screen->in('header')->add($breadcrumb);

    $this->assertSame([$breadcrumb], $screen->currentLayout()->in('header')->blocks());
  }

  public function testScreenWithoutLayoutHasNowhereToPutBlock(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('This screen has no layout, so it has no regions to place a block in.');

    (new Screen())->in('header');
  }

  public function testTheShippedLayoutsAreReachableByName(): void {
    $this->assertInstanceOf(DefaultLayout::class, LayoutManager::create('default'));
    $this->assertInstanceOf(TwoColumnLayout::class, LayoutManager::create('two-column'));
  }

  public function testEachCallHandsBackLayoutOfItsOwn(): void {
    // Two forms picking the same layout must not share its regions, or one
    // would see the other's blocks.
    $this->assertNotSame(LayoutManager::create('default'), LayoutManager::create('default'));
  }

  public function testConsumerRegistersItsOwnUnderShortName(): void {
    LayoutManager::register('sidebar', SidebarLayoutFixture::class);

    $this->assertInstanceOf(SidebarLayoutFixture::class, LayoutManager::create('sidebar'));
  }

  public function testClassNameWorksWithoutRegistering(): void {
    $this->assertInstanceOf(SidebarLayoutFixture::class, LayoutManager::create(SidebarLayoutFixture::class));
  }

  public function testAnUnknownNameListsTheOnesThereAre(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown layout "sidebar". Registered: default, two-column.');

    LayoutManager::create('sidebar');
  }

  public function testRegisteringSomethingThatIsNotLayoutIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Layout class "stdClass" must extend ' . AbstractLayout::class . '.');

    LayoutManager::register('bogus', \stdClass::class);
  }

}

/**
 * A layout registered by a consumer rather than shipped.
 */
final class SidebarLayoutFixture extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Columns);

    $this->region('sidebar')->fixed(24);
    $this->region('main')->scrolls();
  }

}
