<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Region;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the region: a named container that flows blocks and may scroll.
 */
#[CoversClass(Region::class)]
#[Group('screen')]
final class RegionTest extends TestCase {

  public function testNameIsHowABlockSaysWhereItGoes(): void {
    $this->assertSame('header', (new Region('header'))->name());
  }

  public function testDeclaringNeitherSizeIsAWeightOfOne(): void {
    $region = new Region('content');

    $this->assertNull($region->fixedSize());
    $this->assertSame(1, $region->flexShare());
  }

  public function testFixedTakesCellsAndClearsAnyShare(): void {
    $region = (new Region('header'))->flex(4)->fixed(1);

    $this->assertSame(1, $region->fixedSize());
    $this->assertNull($region->flexShare());
  }

  public function testFlexTakesAShareAndClearsAnyFixedSize(): void {
    $region = (new Region('content'))->fixed(1)->flex(3);

    $this->assertNull($region->fixedSize());
    $this->assertSame(3, $region->flexShare());
  }

  public function testBlocksFlowDownTheRegionUnlessToldOtherwise(): void {
    $this->assertSame(Axis::Rows, (new Region('content'))->flowAxis());
    $this->assertSame(Axis::Columns, (new Region('header'))->flow(Axis::Columns)->flowAxis());
  }

  public function testARegionIsPinnedUntilItDeclaresItScrolls(): void {
    $this->assertFalse((new Region('header'))->isScrolling());
    $this->assertTrue((new Region('content'))->scrolls()->isScrolling());
  }

  public function testEveryDeclarationChainsBackToTheRegion(): void {
    $region = new Region('content');

    $this->assertSame($region, $region->fixed(1));
    $this->assertSame($region, $region->flex(2));
    $this->assertSame($region, $region->flow(Axis::Columns));
    $this->assertSame($region, $region->scrolls());
  }

  public function testARegionArrivesEmpty(): void {
    $this->assertSame([], (new Region('content'))->blocks());
  }

  public function testARegionTakesEveryKindOfBlockTheSameWay(): void {
    $region = new Region('content');
    $first = new Markup('intro', 'Pick the produce.');
    $second = new Breadcrumb();

    $region->add($first)->add($second);

    $this->assertSame([$first, $second], $region->blocks());
  }

  public function testAddingABlockChainsBackToTheRegion(): void {
    $region = new Region('content');

    $this->assertSame($region, $region->add(new Breadcrumb()));
  }

  public function testFixedRejectsASizeNoRegionCouldHave(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A fixed size is a count of cells, so it cannot be 0.');

    (new Region('header'))->fixed(0);
  }

  public function testFlexRejectsAShareThatWouldTakeNothing(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A flex share divides the remainder, so it cannot be 0.');

    (new Region('content'))->flex(0);
  }

}
