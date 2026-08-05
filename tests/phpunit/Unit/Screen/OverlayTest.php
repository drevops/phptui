<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Screen\Overlay;
use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\VAlign;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the line-compositor that overlays a box on a backdrop.
 */
#[CoversClass(Overlay::class)]
#[Group('screen')]
final class OverlayTest extends TestCase {

  public function testCenterPositionsWithEqualPadding(): void {
    $this->assertSame([1, 3], Overlay::center(10, 4, 4, 2));
  }

  public function testCenterClampsWhenBoxExceedsArea(): void {
    // A box larger than the area cannot be inset: both offsets clamp to zero.
    $this->assertSame([0, 0], Overlay::center(4, 2, 10, 8));
  }

  #[DataProvider('dataProviderPlace')]
  public function testPlace(HAlign $halign, VAlign $valign, array $expected): void {
    // A 4x2 box in a 10x8 area leaves 6 spare columns and 6 spare rows.
    $this->assertSame($expected, Overlay::place(10, 8, 4, 2, $halign, $valign));
  }

  public static function dataProviderPlace(): \Iterator {
    yield 'top left' => [HAlign::Left, VAlign::Top, [0, 0]];
    yield 'top center' => [HAlign::Center, VAlign::Top, [0, 3]];
    yield 'top right' => [HAlign::Right, VAlign::Top, [0, 6]];
    yield 'middle left' => [HAlign::Left, VAlign::Middle, [3, 0]];
    yield 'middle center' => [HAlign::Center, VAlign::Middle, [3, 3]];
    yield 'middle right' => [HAlign::Right, VAlign::Middle, [3, 6]];
    yield 'bottom left' => [HAlign::Left, VAlign::Bottom, [6, 0]];
    yield 'bottom center' => [HAlign::Center, VAlign::Bottom, [6, 3]];
    yield 'bottom right' => [HAlign::Right, VAlign::Bottom, [6, 6]];
  }

  #[DataProvider('dataProviderPlaceClampsWhenBoxExceedsArea')]
  public function testPlaceClampsWhenBoxExceedsArea(HAlign $halign, VAlign $valign): void {
    // A box larger than the area clamps to the top-left on every anchor.
    $this->assertSame([0, 0], Overlay::place(4, 2, 10, 8, $halign, $valign));
  }

  public static function dataProviderPlaceClampsWhenBoxExceedsArea(): \Iterator {
    yield 'center middle' => [HAlign::Center, VAlign::Middle];
    yield 'right bottom' => [HAlign::Right, VAlign::Bottom];
  }

  public function testCompositeSplicesBoxAndStylesTheExposedBackdrop(): void {
    $backdrop = ['0123456789', 'abcdefghij', 'ABCDEFGHIJ', 'klmnopqrst'];
    $box = ['[XX]', '[YY]'];

    $out = Overlay::composite($backdrop, $box, 4, 1, 3, static fn(string $segment): string => '<' . $segment . '>');

    // Rows outside the box's vertical span are wholly styled; within it the box
    // sits verbatim between a styled prefix and suffix sliced by column.
    $this->assertSame([
      '<0123456789>',
      '<abc>[XX]<hij>',
      '<ABC>[YY]<HIJ>',
      '<klmnopqrst>',
    ], $out);
  }

  public function testCompositeLeavesAnEmptyEdgeUnstyled(): void {
    // At the left edge the prefix is empty, so no styling wraps nothing.
    $out = Overlay::composite(['abcde'], ['[X]'], 3, 0, 0, static fn(string $segment): string => '<' . $segment . '>');

    $this->assertSame(['[X]<de>'], $out);
  }

  public function testCompositeClipsBoxRowsPastTheBackdrop(): void {
    // A box taller than the backdrop is clipped: the backdrop bounds it.
    $out = Overlay::composite(['aaaa'], ['[XX]', '[YY]'], 4, 0, 0, static fn(string $segment): string => $segment);

    $this->assertSame(['[XX]'], $out);
  }

}
