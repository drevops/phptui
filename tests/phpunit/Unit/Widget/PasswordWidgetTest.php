<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;
use DrevOps\Tui\Widget\PasswordWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the password widget.
 */
#[CoversClass(PasswordWidget::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[Group('widget')]
final class PasswordWidgetTest extends TestCase {

  public function testAcceptsPlainValue(): void {
    $widget = new PasswordWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('s3cret', Key::named(KeyName::Enter)));

    $this->assertSame('s3cret', $value);
  }

  public function testMaskedViewCountsCharactersNotBytes(): void {
    $widget = new PasswordWidget('éé');

    // Two characters mask as exactly two glyphs, whatever their byte length.
    $this->assertSame('**|', $widget->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE])));
  }

  public function testViewMasksEveryCharacter(): void {
    $widget = new PasswordWidget('abc');

    $view = $widget->view(new DefaultTheme());

    $this->assertStringNotContainsString('abc', $view);
    $this->assertStringNotContainsString('a', $view);
    $this->assertSame(3, substr_count($view, '•'));
    $this->assertStringContainsString('█', $view);
  }

  public function testValidationErrorShownUnderMask(): void {
    $widget = (new PasswordWidget(''))->setHandlers(validate: fn(mixed $value): ?string => is_string($value) && $value !== '' ? NULL : 'Required.');

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Required.', $widget->view(new DefaultTheme()));
  }

  public function testRevealToggleCyclesDisplayModes(): void {
    $widget = new PasswordWidget('abc', revealable: TRUE);
    $theme = new DefaultTheme();

    // Masked by default: one glyph per character, the value never shown.
    $this->assertSame(3, substr_count($widget->view($theme), '•'));
    $this->assertStringNotContainsString('abc', $widget->view($theme));

    // Tab reveals the plaintext.
    $widget->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('abc', $widget->view($theme));

    // Tab again hides it entirely: neither the value nor its length shows.
    $widget->handle(Key::named(KeyName::Tab));
    $hidden = $widget->view($theme);
    $this->assertStringNotContainsString('abc', $hidden);
    $this->assertStringNotContainsString('•', $hidden);

    // Tab a third time returns to the masked default.
    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame(3, substr_count($widget->view($theme), '•'));
  }

  public function testToggleIgnoredWhenNotRevealable(): void {
    $widget = new PasswordWidget('abc');
    $theme = new DefaultTheme();

    $widget->handle(Key::named(KeyName::Tab));

    // Tab neither revealed the value nor was inserted as a character.
    $this->assertSame(3, substr_count($widget->view($theme), '•'));
    $this->assertStringNotContainsString('abc', $widget->view($theme));
  }

  public function testCancel(): void {
    $widget = new PasswordWidget('x');

    WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
  }

  public function testToggleRevealInertWhenNotRevealable(): void {
    $widget = new PasswordWidget('secret');

    $widget->toggleReveal();

    $this->assertStringNotContainsString('secret', $widget->view(new DefaultTheme()));
  }

  public function testRevealDoesNotChangeAcceptedValue(): void {
    $widget = new PasswordWidget('', revealable: TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('sekret', Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    $this->assertSame('sekret', $value);
  }

  public function testHintShownOnlyWhenRevealable(): void {
    $revealable = array_map(static fn(Hint $hint): string => $hint->label, (new PasswordWidget('x', revealable: TRUE))->hints());
    $this->assertContains('reveal', $revealable);

    $plain = array_map(static fn(Hint $hint): string => $hint->label, (new PasswordWidget('x'))->hints());
    $this->assertNotContains('reveal', $plain);
  }

  public function testConfirmAcceptsMatchingEntries(): void {
    $theme = new DefaultTheme();
    $widget = new PasswordWidget('', confirm: TRUE);

    // The first Enter stashes the entry and prompts for a second pass.
    $this->type($widget, 'pw');
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('re-enter to confirm', $widget->view($theme));

    // A matching second entry accepts, with the plain value preserved.
    $this->type($widget, 'pw');
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertSame('pw', $widget->value());
  }

  public function testConfirmRejectsMismatchAndRestarts(): void {
    $theme = new DefaultTheme();
    $widget = new PasswordWidget('', confirm: TRUE);

    $this->type($widget, 'pw');
    $widget->handle(Key::named(KeyName::Enter));
    $this->type($widget, 'zz');
    $widget->handle(Key::named(KeyName::Enter));

    // The mismatch is rejected with a clear message and both entries cleared.
    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Passwords do not match.', $widget->view($theme));
    $this->assertStringNotContainsString('re-enter to confirm', $widget->view($theme));

    // A fresh matching pair now accepts.
    $this->type($widget, 'pw');
    $widget->handle(Key::named(KeyName::Enter));
    $this->type($widget, 'pw');
    $widget->handle(Key::named(KeyName::Enter));
    $this->assertTrue($widget->isComplete());
    $this->assertSame('pw', $widget->value());
  }

  public function testConfirmRevalidatesMatchedValue(): void {
    $theme = new DefaultTheme();
    $widget = (new PasswordWidget('', confirm: TRUE))->setHandlers(validate: fn(mixed $value): string => 'Too weak.');

    $this->type($widget, 'x');
    $widget->handle(Key::named(KeyName::Enter));
    $this->type($widget, 'x');
    $widget->handle(Key::named(KeyName::Enter));

    // Entries match, but the validator still rejects the value and restarts.
    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Too weak.', $widget->view($theme));
    $this->assertStringNotContainsString('re-enter to confirm', $widget->view($theme));
  }

  public function testPlaceholderGhostsAnEmptyBufferInEveryDisplayMode(): void {
    $widget = (new PasswordWidget('', revealable: TRUE))->setPlaceholder('At least 12 characters');
    $theme = new DefaultTheme();

    // An empty buffer hides nothing, so the prompt shows masked, plaintext and
    // hidden alike.
    $this->assertStringContainsString('At least 12 characters', $widget->view($theme));

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('At least 12 characters', $widget->view($theme));

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('At least 12 characters', $widget->view($theme));
  }

  public function testPlaceholderClearsOnceTheEntryIsMasked(): void {
    $widget = (new PasswordWidget())->setPlaceholder('At least 12 characters');

    $this->type($widget, 's3cret');

    $this->assertStringNotContainsString('At least 12 characters', $widget->view(new DefaultTheme()));
  }

  /**
   * Type a run of printable characters into a widget.
   *
   * @param \DrevOps\Tui\Widget\PasswordWidget $widget
   *   The widget.
   * @param string $text
   *   The characters to type.
   */
  protected function type(PasswordWidget $widget, string $text): void {
    foreach (str_split($text) as $char) {
      $widget->handle(Key::char($char));
    }
  }

}
