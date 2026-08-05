<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Capability\TextEditCapableTrait;
use DrevOps\Tui\Field\Password;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the password field.
 */
#[CoversClass(Password::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[Group('field')]
final class PasswordTest extends TestCase {

  public function testAcceptsPlainValue(): void {
    $field = new Password();

    $value = FieldRunner::run($field, ArrayKeyStream::of('s3cret', Key::named(KeyName::Enter)));

    $this->assertSame('s3cret', $value);
  }

  public function testMaskedViewCountsCharactersNotBytes(): void {
    $field = new Password('éé');

    // Two characters mask as exactly two glyphs, whatever their byte length.
    $this->assertSame('**|', $field->view(new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE])));
  }

  public function testViewMasksEveryCharacter(): void {
    $field = new Password('abc');

    $view = $field->view(new DefaultTheme());

    $this->assertStringNotContainsString('abc', $view);
    $this->assertStringNotContainsString('a', $view);
    $this->assertSame(3, substr_count($view, '•'));
    $this->assertStringContainsString('█', $view);
  }

  public function testRefusalIsShownUnderTheMask(): void {
    $field = new Password('secret');

    $field->refused('Required.');

    $view = $field->view(new DefaultTheme());

    $this->assertStringContainsString('Required.', $view);
    $this->assertStringNotContainsString('secret', $view);
  }

  public function testRevealToggleCyclesDisplayModes(): void {
    $field = new Password('abc', revealable: TRUE);
    $theme = new DefaultTheme();

    // Masked by default: one glyph per character, the value never shown.
    $this->assertSame(3, substr_count($field->view($theme), '•'));
    $this->assertStringNotContainsString('abc', $field->view($theme));

    // Tab reveals the plaintext.
    $field->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('abc', $field->view($theme));

    // Tab again hides it entirely: neither the value nor its length shows.
    $field->handle(Key::named(KeyName::Tab));
    $hidden = $field->view($theme);
    $this->assertStringNotContainsString('abc', $hidden);
    $this->assertStringNotContainsString('•', $hidden);

    // Tab a third time returns to the masked default.
    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame(3, substr_count($field->view($theme), '•'));
  }

  public function testToggleIgnoredWhenNotRevealable(): void {
    $field = new Password('abc');
    $theme = new DefaultTheme();

    $field->handle(Key::named(KeyName::Tab));

    // Tab neither revealed the value nor was inserted as a character.
    $this->assertSame(3, substr_count($field->view($theme), '•'));
    $this->assertStringNotContainsString('abc', $field->view($theme));
  }

  public function testCancel(): void {
    $field = new Password('x');

    FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
  }

  public function testToggleRevealInertWhenNotRevealable(): void {
    $field = new Password('secret');

    $field->toggleReveal();

    $this->assertStringNotContainsString('secret', $field->view(new DefaultTheme()));
  }

  public function testRevealDoesNotChangeAcceptedValue(): void {
    $field = new Password('', revealable: TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of('sekret', Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    $this->assertSame('sekret', $value);
  }

  public function testHintShownOnlyWhenRevealable(): void {
    $revealable = array_map(static fn(Hint $hint): string => $hint->label, (new Password('x', revealable: TRUE))->hints());
    $this->assertContains('reveal', $revealable);

    $plain = array_map(static fn(Hint $hint): string => $hint->label, (new Password('x'))->hints());
    $this->assertNotContains('reveal', $plain);
  }

  public function testConfirmAcceptsMatchingEntries(): void {
    $theme = new DefaultTheme();
    $field = new Password('', confirm: TRUE);

    // The first Enter stashes the entry and prompts for a second pass.
    $this->type($field, 'pw');
    $field->handle(Key::named(KeyName::Enter));
    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('re-enter to confirm', $field->view($theme));

    // A matching second entry accepts, with the plain value preserved.
    $this->type($field, 'pw');
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertSame('pw', $field->value());
  }

  public function testConfirmRejectsMismatchAndRestarts(): void {
    $theme = new DefaultTheme();
    $field = new Password('', confirm: TRUE);

    $this->type($field, 'pw');
    $field->handle(Key::named(KeyName::Enter));
    $this->type($field, 'zz');
    $field->handle(Key::named(KeyName::Enter));

    // The mismatch is rejected with a clear message and both entries cleared.
    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('Passwords do not match.', $field->view($theme));
    $this->assertStringNotContainsString('re-enter to confirm', $field->view($theme));

    // A fresh matching pair now accepts.
    $this->type($field, 'pw');
    $field->handle(Key::named(KeyName::Enter));
    $this->type($field, 'pw');
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
    $this->assertSame('pw', $field->value());
  }

  public function testConfirmOffersTheMatchedValueForTheFieldToMeasure(): void {
    $field = new Password('', confirm: TRUE);

    $this->type($field, 'x');
    $field->handle(Key::named(KeyName::Enter));
    $this->type($field, 'x');
    $field->handle(Key::named(KeyName::Enter));

    // Matching is the whole of what the confirmation decides; whether the
    // value is strong enough is measured where the answer is held.
    $this->assertTrue($field->isComplete());
    $this->assertSame('x', $field->value());
    $this->assertNull($field->error());
  }

  public function testPlaceholderGhostsAnEmptyBufferInEveryDisplayMode(): void {
    $field = (new Password('', revealable: TRUE))->setPlaceholder('At least 12 characters');
    $theme = new DefaultTheme();

    // An empty buffer hides nothing, so the prompt shows masked, plaintext and
    // hidden alike.
    $this->assertStringContainsString('At least 12 characters', $field->view($theme));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('At least 12 characters', $field->view($theme));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertStringContainsString('At least 12 characters', $field->view($theme));
  }

  public function testPlaceholderClearsOnceTheEntryIsMasked(): void {
    $field = (new Password())->setPlaceholder('At least 12 characters');

    $this->type($field, 's3cret');

    $this->assertStringNotContainsString('At least 12 characters', $field->view(new DefaultTheme()));
  }

  /**
   * Type a run of printable characters into a field.
   *
   * @param \DrevOps\Tui\Field\Password $field
   *   The field.
   * @param string $text
   *   The characters to type.
   */
  protected function type(Password $field, string $text): void {
    foreach (str_split($text) as $char) {
      $field->handle(Key::char($char));
    }
  }

}
