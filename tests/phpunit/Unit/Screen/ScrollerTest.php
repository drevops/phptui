<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Screen;

use DrevOps\PhpTui\Screen\Scroller;
use DrevOps\PhpTui\Screen\Viewport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scroller and viewport.
 */
#[CoversClass(Scroller::class)]
#[CoversClass(Viewport::class)]
#[Group('screen')]
final class ScrollerTest extends TestCase {

  public function testCursorAtTop(): void {
    $viewport = (new Scroller())->follow(10, 4, 0, 0);

    $this->assertSame(0, $viewport->offset);
    $this->assertFalse($viewport->hasAbove);
    $this->assertTrue($viewport->hasBelow);
  }

  public function testFollowsCursorDown(): void {
    $viewport = (new Scroller())->follow(10, 4, 6, 0);

    $this->assertSame(3, $viewport->offset);
    $this->assertTrue($viewport->hasAbove);
    $this->assertTrue($viewport->hasBelow);
  }

  public function testFollowsCursorUp(): void {
    $this->assertSame(2, (new Scroller())->follow(10, 4, 2, 5)->offset);
  }

  public function testCursorAtBottom(): void {
    $viewport = (new Scroller())->follow(10, 4, 9, 0);

    $this->assertSame(6, $viewport->offset);
    $this->assertTrue($viewport->hasAbove);
    $this->assertFalse($viewport->hasBelow);
  }

  public function testEmptyOrZeroHeight(): void {
    $this->assertFalse((new Scroller())->follow(0, 4, 0, 0)->hasBelow);
    $this->assertFalse((new Scroller())->follow(10, 0, 0, 0)->hasBelow);
  }

  public function testViewportClampsAndFlags(): void {
    $scroller = new Scroller();

    // An overshooting offset clamps to the last full window.
    $this->assertSame(6, $scroller->viewport(9, 10, 4)->offset);
    $this->assertTrue($scroller->viewport(3, 10, 4)->hasAbove);
    $this->assertTrue($scroller->viewport(3, 10, 4)->hasBelow);
    $this->assertFalse($scroller->viewport(0, 10, 4)->hasAbove);
    $this->assertFalse($scroller->viewport(6, 10, 4)->hasBelow);
    $this->assertFalse($scroller->viewport(0, 0, 4)->hasBelow);
  }

  public function testSlice(): void {
    $this->assertSame(['c', 'd'], (new Scroller())->slice(['a', 'b', 'c', 'd', 'e'], 2, 2));
  }

}
