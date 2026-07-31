<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
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
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(static fn(): bool => FALSE),
    );

    $this->assertSame(['organic' => TRUE], (new Collector())->collect($panel));
  }

  public function testSuppliedValuesAreOfferedRatherThanTakenOnTrust(): void {
    $panel = $this->panel(
      (new Field('weight', 'Weight', FieldType::Number))
        ->default(1200)
        ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.'),
    );

    $this->assertSame(['weight' => 4000], (new Collector())->collect($panel, ['weight' => 4000]));
  }

  public function testRefusedSuppliedValueSaysWhichFieldAndWhy(): void {
    $panel = $this->panel(
      (new Field('weight', 'Weight', FieldType::Number))
        ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.'),
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot collect "weight": Enter at least 200.');

    (new Collector())->collect($panel, ['weight' => 10]);
  }

  public function testConditionIsAnsweredAgainstWhatWasCollectedBeforeIt(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('organic', eq: TRUE)),
    );

    $this->assertSame(['organic' => TRUE, 'certifier' => 'Soil Board'], (new Collector())->collect($panel));

    // The supplied answer is what the condition then sees, so a field that
    // depended on the default is no longer asked for.
    $this->assertSame(['organic' => FALSE], (new Collector())->collect($panel, ['organic' => FALSE]));
  }

  public function testRequiredFieldRefusesAnEmptyAnswerAndSaysWhichOne(): void {
    $panel = $this->panel((new Field('basket', 'Basket contents'))->required());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot collect "basket": Basket contents is required.');

    (new Collector())->collect($panel, ['basket' => '']);
  }

  public function testSuppliedValueIsMeasuredAgainstTheBoundsItWasGiven(): void {
    $panel = $this->panel((new Field('weight', 'Weight', FieldType::Number))->default(1200)->bounds(new NumberBounds(200, 9000)));

    $this->assertSame(['weight' => 4000], (new Collector())->collect($panel, ['weight' => 4000]));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot collect "weight": must be between 200 and 9000.');

    (new Collector())->collect($panel, ['weight' => 10]);
  }

  public function testSuppliedValueIsNormalizedBeforeItIsCollected(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->transform(static fn(mixed $value): mixed => is_string($value) ? trim($value) : $value),
    );

    $this->assertSame(['courier' => 'Valley Runs'], (new Collector())->collect($panel, ['courier' => '  Valley Runs  ']));
  }

  public function testSuppliedValueIsMeasuredAgainstTheEntriesItMayPickFrom(): void {
    $panel = $this->panel(
      (new Field('basket', 'Basket contents', FieldType::Select))->entry('apple', 'Apple')->entry('carrot', 'Carrot')->default('apple'),
    );

    $this->assertSame(['basket' => 'carrot'], (new Collector())->collect($panel, ['basket' => 'carrot']));

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot collect "basket": value "plum" is not one of: apple, carrot');

    (new Collector())->collect($panel, ['basket' => 'plum']);
  }

  public function testFieldsInSubPanelsAreCollectedToo(): void {
    $advanced = $this->panel((new Field('debug', 'Debug'))->default(FALSE));
    $panel = $this->panel((new Field('courier', 'Courier'))->default('Valley Runs'), $advanced);

    $this->assertSame(['courier' => 'Valley Runs', 'debug' => FALSE], (new Collector())->collect($panel));
  }

  public function testSeedingResolvesEveryValueAndRefusesNone(): void {
    $panel = $this->panel(
      (new Field('weight', 'Weight', FieldType::Number))->default(1200)->bounds(new NumberBounds(200, 9000)),
    );

    // A screen has somebody in front of it, so a value it cannot take is
    // something to say on the row holding it rather than grounds for failing.
    [$values] = (new Collector())->seed($panel, ['weight' => 10]);

    $this->assertSame(['weight' => 10], $values);
  }

  public function testSeedingSaysHowEachAnswerCameToBeAndWhoIsThere(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('organic', eq: FALSE)),
    );

    [$values, $provenance, $active] = (new Collector())->seed($panel, ['courier' => 'Coast Runs']);

    // A field a condition hides keeps the value it settled on, so a condition
    // satisfied later surfaces a row that already knows its answer.
    $this->assertSame(['organic' => TRUE, 'courier' => 'Coast Runs', 'certifier' => 'Soil Board'], $values);
    $this->assertSame(['organic' => Provenance::Default, 'courier' => Provenance::Edited], $provenance);
    $this->assertSame(['organic' => TRUE, 'courier' => TRUE, 'certifier' => FALSE], $active);
  }

  public function testCollectingBuildsNoScreenAtAll(): void {
    // A layout arranges drawing, so headlessly there is nothing for it to do.
    // The layout answers by recording the question rather than by staying
    // empty: an empty region would read the same whether it was consulted or
    // not, and what is being claimed is that it never was.
    $layout = new class(Axis::Rows) extends AbstractLayout {

      /**
       * How many times the sizes were asked for.
       */
      public int $arranged = 0;

      public function __construct(Axis $axis) {
        parent::__construct($axis);
        $this->region('content')->flex(1);
      }

      public function arrange(int $available): array {
        $this->arranged++;

        return parent::arrange($available);
      }

    };

    $panel = (new Panel('main', 'Delivery'))->layout($layout);
    $panel->in('content')->add((new Field('courier', 'Courier'))->default('Valley Runs'));

    $this->assertSame(['courier' => 'Valley Runs'], (new Collector())->collect($panel));
    $this->assertSame(0, $layout->arranged);
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
