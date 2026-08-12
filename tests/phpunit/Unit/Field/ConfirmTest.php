<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Field;

use DrevOps\PhpTui\Field\AbstractField;
use DrevOps\PhpTui\Field\Confirm;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Testing\ArrayKeyStream;
use DrevOps\PhpTui\Testing\FieldRunner;
use DrevOps\PhpTui\Theme\DefaultTheme;
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

  public function testRefusalIsShownBesideTheChoice(): void {
    $field = new Confirm(FALSE);

    $field->refused('Not allowed.');

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
