<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\CollectException;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
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

  public function testSectionItsConditionHidesIsNeverAskedForEither(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      $this->panel((new Field('certifier', 'Certifier'))->default('Soil Board'))->when(new Condition('organic', eq: TRUE)),
      // Written after the section, so a rule that reached past what it holds
      // would take this question with it.
      (new Field('quantity', 'Quantity', FieldType::Number))->default(6),
    );

    // Nothing the section holds is collected while it is not there, and every
    // question in it is asked again the moment the answers bring it back.
    $this->assertSame(['organic' => FALSE, 'quantity' => 6], (new Collector())->collect($panel));
    $this->assertSame(['organic' => TRUE, 'quantity' => 6, 'certifier' => 'Soil Board'], (new Collector())->collect($panel, ['organic' => TRUE]));
  }

  public function testQuestionInsideSectionWaitsOnBothRules(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      (new Field('exported', 'Exported?', FieldType::Confirm))->default(FALSE),
      $this->panel(
        (new Field('certifier', 'Certifier'))->default('Soil Board'),
        (new Field('customs', 'Customs code'))->default('CT-14')->when(new Condition('exported', eq: TRUE)),
      )->when(new Condition('organic', eq: TRUE)),
    );

    $collected = (new Collector())->collect($panel, ['exported' => TRUE]);

    // The section is there, so its unconditional question is asked and the one
    // carrying a rule of its own is asked once that rule holds too.
    $this->assertSame(['organic' => TRUE, 'exported' => TRUE, 'certifier' => 'Soil Board', 'customs' => 'CT-14'], $collected);

    // With the section gone neither is asked, however its own rule reads.
    $this->assertSame(['organic' => FALSE, 'exported' => TRUE], (new Collector())->collect($panel, ['organic' => FALSE, 'exported' => TRUE]));
  }

  public function testSectionInsideSectionGoesWithTheOneAroundIt(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      $this->panel(
        (new Field('certifier', 'Certifier'))->default('Soil Board'),
        $this->panel((new Field('renewal', 'Renewal date'))->default('2026-07-15')),
      )->when(new Condition('organic', eq: TRUE)),
    );

    // The inner section carries no rule of its own, so what decides it is the
    // one it sits in.
    $this->assertSame(['organic' => FALSE], (new Collector())->collect($panel));
    $this->assertSame(['organic' => TRUE, 'certifier' => 'Soil Board', 'renewal' => '2026-07-15'], (new Collector())->collect($panel, ['organic' => TRUE]));
  }

  public function testValueSuppliedForSectionThatIsNotThereIsNeverRefused(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      $this->panel(
        (new Field('weight', 'Weight', FieldType::Number))
          ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.'),
      )->when(new Condition('organic', eq: TRUE)),
    );

    // Not collected, not refused, not in the result: a question nobody asked
    // cannot have been answered wrongly.
    $this->assertSame(['organic' => FALSE], (new Collector())->collect($panel, ['weight' => 10]));
  }

  public function testSectionComesBackAsTheAnswersItWaitsOnSettle(): void {
    $panel = $this->panel(
      (new Field('category', 'Category'))->default('vegetable'),
      (new Field('grade', 'Grade'))->derive(new Derive('{{category}}', 'upper')),
      $this->panel((new Field('heat', 'Heat level'))->default('mild'))->when(new Condition('grade', eq: 'VEGETABLE')),
    );

    // The answer the section waits on is computed rather than given, so the
    // section enters the settling on a later pass than the fields do.
    $this->assertSame(['category' => 'vegetable', 'grade' => 'VEGETABLE', 'heat' => 'mild'], (new Collector())->collect($panel));
    $this->assertSame(['category' => 'fruit', 'grade' => 'FRUIT'], (new Collector())->collect($panel, ['category' => 'fruit']));
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

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "weight": Enter at least 200.');

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

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "basket": Basket contents is required.');

    (new Collector())->collect($panel, ['basket' => '']);
  }

  public function testSuppliedValueIsMeasuredAgainstTheBoundsItWasGiven(): void {
    $panel = $this->panel((new Field('weight', 'Weight', FieldType::Number))->default(1200)->bounds(new NumberBounds(200, 9000)));

    $this->assertSame(['weight' => 4000], (new Collector())->collect($panel, ['weight' => 4000]));

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "weight": Enter a number between 200 and 9000.');

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

    $this->expectException(CollectException::class);
    $this->expectExceptionMessage('Invalid value for field "basket": value "plum" is not one of: apple, carrot');

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
