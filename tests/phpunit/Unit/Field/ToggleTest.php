<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Toggle;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the toggle field.
 */
#[CoversClass(Toggle::class)]
#[CoversClass(AbstractField::class)]
#[Group('field')]
final class ToggleTest extends TestCase {

  public function testDefaultAndFlip(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');
    $this->assertSame('enabled', $field->value());
    $this->assertStringContainsString('● Enabled', Ansi::strip($field->view(new DefaultTheme())));

    $field->handle(Key::named(KeyName::Space));
    $this->assertSame('disabled', $field->value());
    $this->assertStringContainsString('● Disabled', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testHonoursExplicitDefault(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'disabled');

    $this->assertSame('disabled', $field->value());
    $this->assertStringContainsString('● Disabled', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testUnknownDefaultFallsBackToFirst(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'nope');

    $this->assertSame('enabled', $field->value());
  }

  #[DataProvider('dataProviderFlipKeys')]
  public function testFlipKeys(Key $key): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $field->handle($key);

    $this->assertSame('disabled', $field->value());
  }

  /**
   * Data provider for testFlipKeys().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Input\Key}>
   *   Each key that flips the switch.
   */
  public static function dataProviderFlipKeys(): \Iterator {
    yield 'space' => [Key::named(KeyName::Space)];
    yield 'left' => [Key::named(KeyName::Left)];
    yield 'right' => [Key::named(KeyName::Right)];
    yield 'up' => [Key::named(KeyName::Up)];
    yield 'down' => [Key::named(KeyName::Down)];
  }

  public function testDirectSelectionByFirstLetter(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $field->handle(Key::char('d'));
    $this->assertSame('disabled', $field->value());

    $field->handle(Key::char('e'));
    $this->assertSame('enabled', $field->value());

    // Selection is case-insensitive.
    $field->handle(Key::char('D'));
    $this->assertSame('disabled', $field->value());

    // A letter matching neither label is a no-op.
    $field->handle(Key::char('z'));
    $this->assertSame('disabled', $field->value());
  }

  public function testFirstLetterCollisionSelectsFirstLabel(): void {
    $field = new Toggle(['public' => 'Public', 'private' => 'Private'], 'private');

    // Both labels start with "p"; the first-declared label wins.
    $field->handle(Key::char('p'));
    $this->assertSame('public', $field->value());

    // The colliding label stays reachable by flipping.
    $field->handle(Key::named(KeyName::Space));
    $this->assertSame('private', $field->value());
  }

  public function testAccept(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame('disabled', $value);
    $this->assertTrue($field->isComplete());
  }

  public function testCancel(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testAsciiRendering(): void {
    $field = new Toggle(['enabled' => 'Enabled', 'disabled' => 'Disabled'], 'enabled');
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $view = $field->view($theme);

    $this->assertStringContainsString('(*) Enabled', $view);
    $this->assertStringContainsString('( ) Disabled', $view);
  }

  public function testFlipWithoutOptionsIsSafe(): void {
    $field = new Toggle([]);

    $field->handle(Key::named(KeyName::Space));

    $this->assertSame('', $field->value());
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Toggle(['on' => 'On', 'off' => 'Off']))->hints());

    $this->assertSame(['toggle', 'accept', 'cancel'], $labels);
  }

}
