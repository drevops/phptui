<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the field: the only block that collects, and the only one with modes.
 */
#[CoversClass(Field::class)]
#[Group('block')]
final class FieldBlockTest extends TestCase {

  public function testFieldStartsInViewModeShowingWhatWasAccepted(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $this->assertSame(Mode::View, $field->mode());
    $this->assertSame('Courier  Valley Runs', $field->render($this->theme()));
  }

  public function testOpeningFieldSwitchesItToEditMode(): void {
    $field = new Field('courier', 'Courier');

    $this->assertSame(Mode::Edit, $field->open()->mode());
    $this->assertSame(Mode::View, $field->close()->mode());
  }

  public function testTheLabelStaysPutAcrossBothModes(): void {
    $field = (new Field('basket', 'Basket'))->entry('apple', 'Apple');

    $this->assertStringStartsWith('Basket', $field->render($this->theme()));
    $this->assertStringStartsWith('Basket', $field->open()->render($this->theme()));
  }

  public function testEditModeOpensOntoTheEntriesItWasGiven(): void {
    $field = (new Field('basket', 'Basket'))->entry('apple', 'Apple')->entry('carrot', 'Carrot');

    $rendered = $field->open()->render($this->theme());

    $this->assertStringContainsString('Apple', $rendered);
    $this->assertStringContainsString('Carrot', $rendered);
  }

  public function testOnlyWhatWasAcceptedReachesTheResult(): void {
    $field = new Field('courier', 'Courier');

    $this->assertNull($field->value());

    $this->assertTrue($field->accept('Valley Runs'));
    $this->assertSame('Valley Runs', $field->value());
  }

  public function testDraftIsDiscardedUnlessItIsAccepted(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $field->open()->draft('Coast Runs');
    $this->assertSame('Valley Runs', $field->value());

    $field->close();
    $this->assertSame('Valley Runs', $field->value());
  }

  public function testAcceptingDraftMakesItTheValue(): void {
    $field = (new Field('courier', 'Courier'))->default('Valley Runs');

    $field->open()->draft('Coast Runs');

    $this->assertTrue($field->accept());
    $this->assertSame('Coast Runs', $field->value());
    $this->assertSame(Mode::View, $field->mode());
  }

  public function testConstraintSaysWhatIsAcceptableBeforeYouAct(): void {
    $field = (new Field('weight', 'Weight'))->constrain('a number between 200 and 9000');

    $this->assertSame('a number between 200 and 9000', $field->constraint());
    $this->assertNull($field->error());
  }

  public function testRefusedValueIsExplainedAndDoesNotBecomeTheValue(): void {
    $field = (new Field('weight', 'Weight'))
      ->default(1200)
      ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.');

    $this->assertFalse($field->accept(10));
    $this->assertSame('Enter at least 200.', $field->error());
    $this->assertSame(1200, $field->value());
  }

  public function testAnErrorClearsAsSoonAsValueIsAcceptable(): void {
    $field = (new Field('weight', 'Weight'))
      ->validate(static fn(mixed $value): ?string => is_int($value) && $value >= 200 ? NULL : 'Enter at least 200.');

    $field->accept(10);
    $this->assertNotNull($field->error());

    $this->assertTrue($field->accept(400));
    $this->assertNull($field->error());
  }

  public function testFieldIsAskedForUnlessItsConditionSaysOtherwise(): void {
    $field = new Field('organic', 'Organic only?');

    $this->assertTrue($field->isActive());
    $this->assertFalse($field->when(static fn(): bool => FALSE)->isActive());
  }

  public function testHelpIsNeverDrawnInTheRowThatOffersIt(): void {
    $field = (new Field('basket', 'Basket'))->help('Every crate is weighed at the packing bench.');

    $this->assertSame('Every crate is weighed at the packing bench.', $field->helpText());
    $this->assertStringNotContainsString('packing bench', $field->render($this->theme()));
  }

  /**
   * A theme with colour off, so the assertions read as plain strings.
   */
  protected function theme(): DefaultTheme {
    return new DefaultTheme(80, ['color' => FALSE]);
  }

}
