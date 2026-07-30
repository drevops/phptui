<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\DefaultLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests collecting with no screen: only what a form means survives.
 */
#[CoversClass(Collector::class)]
#[Group('screen')]
final class CollectorTest extends TestCase {

  public function testEveryFieldContributesItsAnswer(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('weight', 'Weight'))->default(1200),
    );

    $this->assertSame(['courier' => 'Valley Runs', 'weight' => 1200], (new Collector())->collect($panel));
  }

  public function testNothingThatOnlyShowsReachesTheResult(): void {
    $panel = $this->panel(
      new Breadcrumb('Orchard'),
      new Markup('intro', 'Pick the produce.'),
      new Legend(),
      (new Field('courier', 'Courier'))->default('Valley Runs'),
    );

    $this->assertSame(['courier' => 'Valley Runs'], (new Collector())->collect($panel));
  }

  public function testWorkThatOnlyActivatesReachesNothingEither(): void {
    $panel = $this->panel(
      new Progress('packing', 'Packing crates'),
      (new Field('courier', 'Courier'))->default('Valley Runs'),
    );

    $this->assertSame(['courier' => 'Valley Runs'], (new Collector())->collect($panel));
  }

  public function testFieldItsConditionHidesIsNeverAskedFor(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?'))->default(TRUE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(static fn(): bool => FALSE),
    );

    $this->assertSame(['organic' => TRUE], (new Collector())->collect($panel));
  }

  public function testSuppliedValuesAreOfferedRatherThanTakenOnTrust(): void {
    $panel = $this->panel(
      (new Field('weight', 'Weight'))
        ->default(1200)
        ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.'),
    );

    $this->assertSame(['weight' => 4000], (new Collector())->collect($panel, ['weight' => 4000]));
  }

  public function testRefusedSuppliedValueSaysWhichFieldAndWhy(): void {
    $panel = $this->panel(
      (new Field('weight', 'Weight'))
        ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.'),
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot collect "weight": Enter at least 200.');

    (new Collector())->collect($panel, ['weight' => 10]);
  }

  public function testFieldsInSubPanelsAreCollectedToo(): void {
    $advanced = $this->panel((new Field('debug', 'Debug'))->default(FALSE));
    $panel = $this->panel((new Field('courier', 'Courier'))->default('Valley Runs'), $advanced);

    $this->assertSame(['courier' => 'Valley Runs', 'debug' => FALSE], (new Collector())->collect($panel));
  }

  public function testCollectingBuildsNoScreenAtAll(): void {
    // A layout arranges drawing, so headlessly there is nothing for it to do:
    // the collector never asks a panel for its layout's sizes.
    $panel = $this->panel((new Field('courier', 'Courier'))->default('Valley Runs'));

    $this->assertSame(['courier' => 'Valley Runs'], (new Collector())->collect($panel));
    $this->assertSame([], $panel->currentLayout()->in('header')->blocks());
  }

  /**
   * A panel holding the given blocks in its content region.
   */
  protected function panel(object ...$blocks): Panel {
    $panel = (new Panel('main', 'Delivery'))->layout(new DefaultLayout());

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return $panel;
  }

}
