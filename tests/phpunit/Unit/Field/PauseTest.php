<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Field;

use DrevOps\PhpTui\Field\AbstractField;
use DrevOps\PhpTui\Field\Pause;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Testing\ArrayKeyStream;
use DrevOps\PhpTui\Testing\FieldRunner;
use DrevOps\PhpTui\Theme\DefaultTheme;
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
