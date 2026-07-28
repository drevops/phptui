<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\NumberWidget;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the number widget.
 */
#[CoversClass(NumberWidget::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[Group('widget')]
final class NumberWidgetTest extends TestCase {

  public function testTypesDigitsAndAcceptsInt(): void {
    $widget = new NumberWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('8080', Key::named(KeyName::Enter)));

    $this->assertSame(8080, $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testRejectsNonDigits(): void {
    $widget = new NumberWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('4a2 x!', Key::named(KeyName::Enter)));

    $this->assertSame(42, $value);
  }

  public function testLeadingMinusOnly(): void {
    $widget = new NumberWidget();

    $widget->handle(Key::char('-'));
    $widget->handle(Key::char('7'));
    // A second minus, no longer at the start, is ignored.
    $widget->handle(Key::char('-'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertSame(-7, $widget->value());
  }

  public function testMinusRejectedMidBuffer(): void {
    $widget = new NumberWidget('12');

    $widget->handle(Key::named(KeyName::Left));
    $widget->handle(Key::named(KeyName::Left));
    // The cursor is at the start, but a minus cannot join an existing one.
    $widget->handle(Key::char('-'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertSame(-12, $widget->value());
  }

  public function testEmptyBufferAcceptsZero(): void {
    $widget = new NumberWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(0, $value);
  }

  public function testSeededFromCurrentAndRendersCaret(): void {
    $widget = new NumberWidget('42');

    $this->assertStringContainsString('42', $widget->view(new DefaultTheme()));
    $this->assertStringContainsString('█', $widget->view(new DefaultTheme()));
  }

  public function testArrowsInertAndUnhintedWithoutBounds(): void {
    $widget = new NumberWidget('5');

    // With no bounds the arrows fall through to the inert text handling.
    $widget->handle(Key::named(KeyName::Up));
    $widget->handle(Key::named(KeyName::Down));

    $this->assertSame(5, $widget->value());

    // Without bounds it contributes only the shared accept/cancel hints.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, $widget->hints());
    $this->assertSame(['accept', 'cancel'], $labels);
  }

  public function testStepByInertWithoutBounds(): void {
    $widget = new NumberWidget('5');

    $widget->stepBy(1);

    $this->assertSame(5, $widget->value());
  }

  public function testCancel(): void {
    $widget = new NumberWidget('5');

    WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
  }

  public function testUpDownStepByOneWithinBounds(): void {
    $widget = new NumberWidget('5', bounds: new NumberBounds(0, 10));

    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame(6, $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame(5, $widget->value());
  }

  public function testStepClampsToMax(): void {
    $widget = new NumberWidget('9', bounds: new NumberBounds(0, 10, 3));

    $widget->handle(Key::named(KeyName::Up));

    $this->assertSame(10, $widget->value());
  }

  public function testStepClampsToMin(): void {
    $widget = new NumberWidget('1', bounds: new NumberBounds(0, 10, 3));

    $widget->handle(Key::named(KeyName::Down));

    $this->assertSame(0, $widget->value());
  }

  public function testAcceptsInRangeValue(): void {
    $widget = new NumberWidget('', bounds: new NumberBounds(1, 10));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('5', Key::named(KeyName::Enter)));

    $this->assertSame(5, $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testRejectsOutOfRangeInline(): void {
    $widget = new NumberWidget('', bounds: new NumberBounds(1, 10));

    $widget->handle(Key::char('5'));
    $widget->handle(Key::char('0'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Enter a number between 1 and 10.', $widget->view(new DefaultTheme()));
  }

  public function testSteppingClearsStaleError(): void {
    $widget = new NumberWidget('', bounds: new NumberBounds(1, 10));

    $widget->handle(Key::char('5'));
    $widget->handle(Key::char('0'));
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertStringContainsString('Enter a number', $widget->view(new DefaultTheme()));

    // Stepping produces a clamped, in-range value, so the error clears.
    $widget->handle(Key::named(KeyName::Up));

    $this->assertSame(10, $widget->value());
    $this->assertStringNotContainsString('Enter a number', $widget->view(new DefaultTheme()));
  }

  public function testHintsWhenBounded(): void {
    $widget = new NumberWidget('5', bounds: new NumberBounds(0, 10));

    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], $widget->hints());

    $this->assertSame([
      ['adjust', [Action::Increment, Action::Decrement]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testPlaceholderGhostsAnEmptyBufferOnly(): void {
    $widget = (new NumberWidget())->setPlaceholder('E.g. 1200');

    $this->assertStringContainsString('E.g. 1200', $widget->view(new DefaultTheme()));

    $widget->handle(Key::char('4'));

    $this->assertStringNotContainsString('E.g. 1200', $widget->view(new DefaultTheme()));
  }

}
