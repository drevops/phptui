<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\ConfirmWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the confirm widget.
 */
#[CoversClass(ConfirmWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[Group('widget')]
final class ConfirmWidgetTest extends TestCase {

  public function testDefaultAndToggle(): void {
    $widget = new ConfirmWidget(FALSE);
    $this->assertFalse($widget->value());
    $this->assertStringContainsString('● No', Ansi::strip($widget->view(new DefaultTheme())));

    $widget->handle(Key::named(KeyName::Space));
    $this->assertTrue($widget->value());
    $this->assertStringContainsString('● Yes', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testValidatorErrorShownInView(): void {
    $widget = (new ConfirmWidget(FALSE))->setHandlers(validate: static fn (mixed $value): string => 'Not allowed.');

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Not allowed.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testCharYesNo(): void {
    $widget = new ConfirmWidget(FALSE);

    $widget->handle(Key::char('y'));
    $this->assertTrue($widget->value());

    $widget->handle(Key::char('n'));
    $this->assertFalse($widget->value());

    $widget->handle(Key::char('z'));
    $this->assertFalse($widget->value());
  }

  public function testStepByFlipsOnOddSteps(): void {
    $widget = new ConfirmWidget();

    $widget->stepBy(1);
    $this->assertTrue($widget->value());

    // An even step lands back on the same value.
    $widget->stepBy(2);
    $this->assertTrue($widget->value());

    $widget->stepBy(-1);
    $this->assertFalse($widget->value());
  }

  public function testAccept(): void {
    $widget = new ConfirmWidget(TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertTrue($value);
    $this->assertTrue($widget->isComplete());
  }

  public function testCancel(): void {
    $widget = new ConfirmWidget(FALSE);

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new ConfirmWidget(FALSE))->hints());

    $this->assertSame(['answer yes or no', 'toggle', 'accept', 'cancel'], $labels);
  }

}
