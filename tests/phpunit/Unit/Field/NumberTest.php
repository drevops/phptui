<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Capability\TextEditCapableTrait;
use DrevOps\Tui\Field\Number;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the number field.
 */
#[CoversClass(Number::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[Group('field')]
final class NumberTest extends TestCase {

  public function testTypesDigitsAndAcceptsInt(): void {
    $field = new Number();

    $value = FieldRunner::run($field, ArrayKeyStream::of('8080', Key::named(KeyName::Enter)));

    $this->assertSame(8080, $value);
    $this->assertTrue($field->isComplete());
  }

  public function testRejectsNonDigits(): void {
    $field = new Number();

    $value = FieldRunner::run($field, ArrayKeyStream::of('4a2 x!', Key::named(KeyName::Enter)));

    $this->assertSame(42, $value);
  }

  public function testLeadingMinusOnly(): void {
    $field = new Number();

    $field->handle(Key::char('-'));
    $field->handle(Key::char('7'));
    // A second minus, no longer at the start, is ignored.
    $field->handle(Key::char('-'));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertSame(-7, $field->value());
  }

  public function testMinusRejectedMidBuffer(): void {
    $field = new Number('12');

    $field->handle(Key::named(KeyName::Left));
    $field->handle(Key::named(KeyName::Left));
    // The cursor is at the start, but a minus cannot join an existing one.
    $field->handle(Key::char('-'));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertSame(-12, $field->value());
  }

  public function testEmptyBufferAcceptsZero(): void {
    $field = new Number();

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(0, $value);
  }

  public function testSeededFromCurrentAndRendersCaret(): void {
    $field = new Number('42');

    $this->assertStringContainsString('42', $field->view(new DefaultTheme()));
    $this->assertStringContainsString('█', $field->view(new DefaultTheme()));
  }

  public function testArrowsInertAndUnhintedWithoutBounds(): void {
    $field = new Number('5');

    // With no bounds the arrows fall through to the inert text handling.
    $field->handle(Key::named(KeyName::Up));
    $field->handle(Key::named(KeyName::Down));

    $this->assertSame(5, $field->value());

    // Without bounds it contributes only the shared accept/cancel hints.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $field->hints());
    $this->assertSame(['accept', 'cancel'], $labels);
  }

  public function testStepByInertWithoutBounds(): void {
    $field = new Number('5');

    $field->stepBy(1);

    $this->assertSame(5, $field->value());
  }

  public function testCancel(): void {
    $field = new Number('5');

    FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
  }

  public function testUpDownStepByOneWithinBounds(): void {
    $field = new Number('5', bounds: new NumberBounds(0, 10));

    $field->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $field->value());

    $field->handle(Key::named(KeyName::Down));
    $this->assertSame(5, $field->value());
  }

  public function testStepClampsToMax(): void {
    $field = new Number('9', bounds: new NumberBounds(0, 10, 3));

    $field->handle(Key::named(KeyName::Up));

    $this->assertSame(10, $field->value());
  }

  public function testStepClampsToMin(): void {
    $field = new Number('1', bounds: new NumberBounds(0, 10, 3));

    $field->handle(Key::named(KeyName::Down));

    $this->assertSame(0, $field->value());
  }

  public function testAcceptsInRangeValue(): void {
    $field = new Number('', bounds: new NumberBounds(1, 10));

    $value = FieldRunner::run($field, ArrayKeyStream::of('5', Key::named(KeyName::Enter)));

    $this->assertSame(5, $value);
    $this->assertTrue($field->isComplete());
  }

  public function testOffersAnOutOfRangeValueRatherThanRefusingIt(): void {
    $field = new Number('', bounds: new NumberBounds(1, 10));

    $field->handle(Key::char('5'));
    $field->handle(Key::char('0'));
    $field->handle(Key::named(KeyName::Enter));

    // The bounds move the value here and never measure one: what an answer
    // must be is measured where the answer is held.
    $this->assertTrue($field->isComplete());
    $this->assertSame(50, $field->value());
    $this->assertNull($field->error());
  }

  public function testSteppingClearsStaleError(): void {
    $field = new Number('50', bounds: new NumberBounds(1, 10));

    $field->refused('Enter a number between 1 and 10.');
    $this->assertStringContainsString('Enter a number', $field->view(new DefaultTheme()));

    // Stepping produces a clamped, in-range value, so the error clears.
    $field->handle(Key::named(KeyName::Up));

    $this->assertSame(10, $field->value());
    $this->assertStringNotContainsString('Enter a number', $field->view(new DefaultTheme()));
  }

  public function testHintsWhenBounded(): void {
    $field = new Number('5', bounds: new NumberBounds(0, 10));

    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], $field->hints());

    $this->assertSame([
      ['adjust', [Action::Increment, Action::Decrement]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testPlaceholderGhostsAnEmptyBufferOnly(): void {
    $field = (new Number())->setPlaceholder('E.g. 1200');

    $this->assertStringContainsString('E.g. 1200', $field->view(new DefaultTheme()));

    $field->handle(Key::char('4'));

    $this->assertStringNotContainsString('E.g. 1200', $field->view(new DefaultTheme()));
  }

}
