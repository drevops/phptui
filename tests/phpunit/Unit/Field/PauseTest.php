<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Pause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pause field.
 */
#[CoversClass(Pause::class)]
#[CoversClass(AbstractField::class)]
#[Group('field')]
final class PauseTest extends TestCase {

  public function testEnterAcknowledges(): void {
    $field = new Pause();

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertTrue($value);
    $this->assertTrue($field->isComplete());
  }

  public function testSpaceAcknowledges(): void {
    $field = new Pause();

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Space)));

    $this->assertTrue($value);
  }

  public function testOtherKeysIgnored(): void {
    $field = new Pause();

    $field->handle(Key::char('x'));
    $field->handle(Key::named(KeyName::Down));

    $this->assertFalse($field->isComplete());
    $this->assertFalse($field->value());
  }

  public function testCancelAndView(): void {
    $field = new Pause();

    // The prompt key glyph is drawn from the live binding (Enter by default).
    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('Press ', $view);
    $this->assertStringContainsString('to continue', $view);
    $this->assertStringContainsString('↵', $view);

    $field->handle(Key::named(KeyName::Escape));
    $this->assertTrue($field->isCancelled());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Pause())->hints());

    $this->assertSame(['continue', 'cancel'], $labels);
  }

}
