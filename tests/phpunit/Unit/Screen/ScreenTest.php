<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Screen\Screen;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the screen root: one layout, and whether it occupies the terminal.
 */
#[CoversClass(Screen::class)]
#[Group('screen')]
final class ScreenTest extends TestCase {

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

}
