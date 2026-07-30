<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\ThemeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the blocks: each composes the theme's elements into its own output.
 */
#[CoversClass(Breadcrumb::class)]
#[CoversClass(Legend::class)]
#[CoversClass(Markup::class)]
#[CoversClass(Actions::class)]
#[CoversClass(Progress::class)]
#[Group('block')]
final class BlockTest extends TestCase {

  public function testABreadcrumbJoinsItsSegments(): void {
    $theme = $this->theme();

    $this->assertSame('Orchard › Delivery', (new Breadcrumb('Orchard', 'Delivery'))->render($theme));
  }

  public function testABreadcrumbOfOneSegmentDrawsNoSeparator(): void {
    $this->assertSame('Orchard', (new Breadcrumb('Orchard'))->render($this->theme()));
  }

  public function testABreadcrumbRedrawsItselfAsTheTrailChanges(): void {
    $breadcrumb = new Breadcrumb('Orchard');

    $this->assertSame('Orchard › Delivery', $breadcrumb->trail('Orchard', 'Delivery')->render($this->theme()));
  }

  public function testALegendReadsAsKeyThenWhatItDoes(): void {
    $legend = (new Legend())->entry('↵', 'accept')->entry('ESC', 'cancel');

    $this->assertSame('↵ to accept · ESC to cancel', $legend->render($this->theme()));
  }

  public function testALegendWithNoKeysDrawsNothing(): void {
    $this->assertSame('', (new Legend())->render($this->theme()));
  }

  public function testMarkupDrawsItsBodyAndItsTitleWhenItHasOne(): void {
    $theme = $this->theme();

    $this->assertSame('Weighed at the bench.', (new Markup('note', 'Weighed at the bench.'))->render($theme));
    $this->assertSame("Yields\nTwelve crates.", (new Markup('yields', 'Twelve crates.', 'Yields'))->render($theme));
  }

  public function testMarkupKeepsTheLineBreaksItWasGiven(): void {
    $rendered = (new Markup('note', "First.\nSecond."))->render($this->theme());

    $this->assertSame(['First.', 'Second.'], explode("\n", $rendered));
  }

  public function testActionsFrameEveryLabelAndMarkTheFocusedOne(): void {
    $actions = (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');

    $this->assertSame('[ Submit ]  [ Cancel ]', $actions->render($this->theme()));
  }

  public function testActionsTakeAnyLabelsTheFormDeclares(): void {
    $actions = (new Actions())->action('save', 'Save draft')->action('submit', 'Submit');

    $this->assertSame(['save', 'submit'], $actions->names());
  }

  public function testProgressDrawsABarWhenTheWorkReportsATotal(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(10)->advance(4);

    $this->assertSame('Packing crates [████░░░░░░] 4/10', $progress->render($this->theme()));
  }

  public function testProgressDrawsASpinnerWhenTheLengthIsUnknown(): void {
    $progress = new Progress('fetching', 'Fetching the price list');

    $this->assertSame('⠋ Fetching the price list', $progress->render($this->theme()));
  }

  public function testProgressAdvancesNoFurtherThanItsTotal(): void {
    $progress = (new Progress('packing', 'Packing'))->steps(3)->advance(9);

    $this->assertSame('Packing [██████████] 3/3', $progress->render($this->theme()));
  }

  public function testABlockRefusesAThemeThatCannotDrawIt(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot draw a breadcrumb');

    // A theme is only required to be a ThemeInterface; the elements a block
    // needs are declared separately, so one that declares none cannot draw it.
    (new Breadcrumb('Orchard'))->render($this->createStub(ThemeInterface::class));
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

}
