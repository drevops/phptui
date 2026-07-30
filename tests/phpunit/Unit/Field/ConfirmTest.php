<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Confirm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the confirm field.
 */
#[CoversClass(Confirm::class)]
#[CoversClass(AbstractField::class)]
#[Group('field')]
final class ConfirmTest extends TestCase {

  public function testDefaultAndToggle(): void {
    $field = new Confirm(FALSE);
    $this->assertFalse($field->value());
    $this->assertStringContainsString('● No', Ansi::strip($field->view(new DefaultTheme())));

    $field->handle(Key::named(KeyName::Space));
    $this->assertTrue($field->value());
    $this->assertStringContainsString('● Yes', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testValidatorErrorShownInView(): void {
    $field = (new Confirm(FALSE))->setHandlers(validate: static fn (mixed $value): string => 'Not allowed.');

    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Not allowed.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testCharYesNo(): void {
    $field = new Confirm(FALSE);

    $field->handle(Key::char('y'));
    $this->assertTrue($field->value());

    $field->handle(Key::char('n'));
    $this->assertFalse($field->value());

    $field->handle(Key::char('z'));
    $this->assertFalse($field->value());
  }

  public function testStepByFlipsOnOddSteps(): void {
    $field = new Confirm();

    $field->stepBy(1);
    $this->assertTrue($field->value());

    // An even step lands back on the same value.
    $field->stepBy(2);
    $this->assertTrue($field->value());

    $field->stepBy(-1);
    $this->assertFalse($field->value());
  }

  public function testAccept(): void {
    $field = new Confirm(TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertTrue($value);
    $this->assertTrue($field->isComplete());
  }

  public function testCancel(): void {
    $field = new Confirm(FALSE);

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Confirm(FALSE))->hints());

    $this->assertSame(['answer yes or no', 'toggle', 'accept', 'cancel'], $labels);
  }

}
