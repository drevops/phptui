<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Capability\TextEditCapableTrait;
use DrevOps\Tui\Field\Textarea;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the textarea field.
 */
#[CoversClass(Textarea::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[Group('field')]
final class TextareaTest extends TestCase {

  public function testEnterInsertsNewlineAndTabAccepts(): void {
    $field = new Textarea();

    $value = FieldRunner::run($field, ArrayKeyStream::of('one', Key::named(KeyName::Enter), 'two', Key::named(KeyName::Tab)));

    $this->assertSame("one\ntwo", $value);
    $this->assertTrue($field->isComplete());
  }

  public function testUpAndDownMoveAcrossLines(): void {
    $field = new Textarea("ab\ncd");

    // The cursor starts at the end of "cd"; Up keeps the column on "ab".
    $field->handle(Key::named(KeyName::Up));
    $field->handle(Key::char('X'));

    $this->assertSame("abX\ncd", $field->value());

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::char('Y'));

    $this->assertSame("abX\ncdY", $field->value());
  }

  public function testUpClampsAtFirstLineAndDownAtLast(): void {
    $field = new Textarea('solo');

    $field->handle(Key::named(KeyName::Up));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Tab));

    $this->assertSame('solo', $field->value());
  }

  public function testUpFromLongerLineClampsColumn(): void {
    $field = new Textarea("a\nlonger");

    $field->handle(Key::named(KeyName::Up));
    $field->handle(Key::char('Z'));

    $this->assertSame("aZ\nlonger", $field->value());
  }

  public function testViewShowsError(): void {
    $field = (new Textarea('x'))->setHandlers(validate: fn(mixed $value): string => 'Nope.');

    $field->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('Nope.', $field->view(new DefaultTheme()));
  }

  public function testCancel(): void {
    $field = new Textarea('x');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
    $this->assertNull($value);
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Textarea('x'))->hints());

    $this->assertSame(['insert a newline', 'accept', 'cancel'], $labels);
  }

  public function testEditorKeyRequestsHandoffWhenEnabled(): void {
    $field = new Textarea('draft', externalEdit: TRUE);

    $field->handle(Key::char("\x05"));

    $this->assertTrue($field->wantsExternalEdit());
    // The buffer is untouched until the captured value is applied.
    $this->assertSame('draft', $field->value());
    $this->assertFalse($field->isComplete());
  }

  public function testEditorKeySwallowedWhenDisabled(): void {
    $field = new Textarea('draft');

    $field->handle(Key::char("\x05"));

    $this->assertFalse($field->wantsExternalEdit());
    // The control key is swallowed, never inserted as a raw byte.
    $this->assertSame('draft', $field->value());
  }

  public function testApplyExternalEditReplacesBufferAndAccepts(): void {
    $field = new Textarea('old', externalEdit: TRUE);
    $field->handle(Key::char("\x05"));

    $field->applyExternalEdit("new\ntext");

    $this->assertSame("new\ntext", $field->value());
    $this->assertTrue($field->isComplete());
    $this->assertFalse($field->wantsExternalEdit());
  }

  public function testApplyExternalEditNullKeepsBufferAndStaysEditing(): void {
    $field = new Textarea('keep', externalEdit: TRUE);
    $field->handle(Key::char("\x05"));

    $field->applyExternalEdit(NULL);

    $this->assertSame('keep', $field->value());
    $this->assertFalse($field->isComplete());
    $this->assertFalse($field->wantsExternalEdit());
  }

  public function testApplyExternalEditRunsValidator(): void {
    $field = (new Textarea('x', externalEdit: TRUE))->setHandlers(validate: fn(mixed $value): string => 'Nope.');

    $field->applyExternalEdit('bad');

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Nope.', $field->view(new DefaultTheme()));
  }

  public function testEditorHintOnlyWhenEnabled(): void {
    $enabled = array_map(static fn(Hint $hint): string => $hint->label, (new Textarea('x', externalEdit: TRUE))->hints());
    $this->assertContains('open the editor', $enabled);

    $disabled = array_map(static fn(Hint $hint): string => $hint->label, (new Textarea('x'))->hints());
    $this->assertNotContains('open the editor', $disabled);
  }

  public function testPlaceholderGhostsAnEmptyBufferOnly(): void {
    $field = (new Textarea())->setPlaceholder('E.g. Crisp and sweet');

    $this->assertStringContainsString('E.g. Crisp and sweet', $field->view(new DefaultTheme()));

    $field->handle(Key::char('C'));

    $this->assertStringNotContainsString('E.g. Crisp and sweet', $field->view(new DefaultTheme()));
  }

}
