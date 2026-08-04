<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Region;
use DrevOps\Tui\Screen\Sizing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the region: a named container that flows blocks and may scroll.
 */
#[CoversClass(Region::class)]
#[Group('screen')]
final class RegionTest extends TestCase {

  public function testNameIsHowBlockSaysWhereItGoes(): void {
    $this->assertSame('header', (new Region('header'))->name());
  }

  public function testDeclaringNeitherSizeIsWeightOfOne(): void {
    $region = new Region('content');

    $this->assertSame(Sizing::Flex, $region->sizing());
    $this->assertSame(1, $region->size());
  }

  public function testFixedTakesCellsAndClearsAnyShare(): void {
    $region = (new Region('header'))->flex(4)->fixed(1);

    $this->assertSame(Sizing::Fixed, $region->sizing());
    $this->assertSame(1, $region->size());
  }

  public function testFlexTakesShareAndClearsAnyFixedSize(): void {
    $region = (new Region('content'))->fixed(1)->flex(3);

    $this->assertSame(Sizing::Flex, $region->sizing());
    $this->assertSame(3, $region->size());
  }

  public function testContentTakesWhatItHoldsAndAsksForNoNumberOfItsOwn(): void {
    $region = (new Region('window-1'))->fixed(4)->content();

    // What it comes to is measured where blocks are drawn, so the declaration
    // carries no number at all.
    $this->assertSame(Sizing::Content, $region->sizing());
    $this->assertSame(0, $region->size());
  }

  public function testRegionDrawsRowsUntilItDeclaresItShowsWhatIsBehindThem(): void {
    $this->assertFalse((new Region('content'))->isPreviewing());
    $this->assertTrue((new Region('window-1'))->previews()->isPreviewing());
  }

  public function testBlocksFlowDownTheRegionUnlessToldOtherwise(): void {
    $this->assertSame(Axis::Rows, (new Region('content'))->flowAxis());
    $this->assertSame(Axis::Columns, (new Region('header'))->flow(Axis::Columns)->flowAxis());
  }

  public function testRegionIsPinnedUntilItDeclaresItScrolls(): void {
    $this->assertFalse((new Region('header'))->isScrolling());
    $this->assertTrue((new Region('content'))->scrolls()->isScrolling());
  }

  public function testEveryDeclarationChainsBackToTheRegion(): void {
    $region = new Region('content');

    $this->assertSame($region, $region->fixed(1));
    $this->assertSame($region, $region->flex(2));
    $this->assertSame($region, $region->content());
    $this->assertSame($region, $region->flow(Axis::Columns));
    $this->assertSame($region, $region->scrolls());
    $this->assertSame($region, $region->previews());
  }

  public function testRegionArrivesEmpty(): void {
    $this->assertSame([], (new Region('content'))->blocks());
  }

  public function testRegionTakesEveryKindOfBlockTheSameWay(): void {
    $region = new Region('content');
    $first = new Markup('intro', 'Pick the produce.');
    $second = new Breadcrumb();

    $region->add($first)->add($second);

    $this->assertSame([$first, $second], $region->blocks());
  }

  public function testAddingBlockChainsBackToTheRegion(): void {
    $region = new Region('content');

    $this->assertSame($region, $region->add(new Breadcrumb()));
    $this->assertSame($region, $region->tail(new Breadcrumb()));
  }

  public function testRegionPacksFromEitherEndOfTheAxisItRunsAlong(): void {
    $region = new Region('footer');
    $legend = new Breadcrumb();
    $version = new Markup('version', 'v1.2.3');

    $region->tail($version)->add($legend);

    // Which end a block was packed from is what says where it sits, so the one
    // written second still leads the region.
    $this->assertSame([$legend], $region->headBlocks());
    $this->assertSame([$version], $region->tailBlocks());
    $this->assertSame([$legend, $version], $region->blocks());
  }

  public function testRegionThatPacksNothingFromTheEndHoldsOnlyItsOwnRun(): void {
    $region = (new Region('content'))->add(new Breadcrumb());

    $this->assertSame([], $region->tailBlocks());
    $this->assertSame($region->headBlocks(), $region->blocks());
  }

  public function testFixedRejectsSizeNoRegionCouldHave(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A fixed size is a count of cells, so it cannot be 0.');

    (new Region('header'))->fixed(0);
  }

  public function testFlexRejectsShareThatWouldTakeNothing(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A flex share divides the remainder, so it cannot be 0.');

    (new Region('content'))->flex(0);
  }

}
