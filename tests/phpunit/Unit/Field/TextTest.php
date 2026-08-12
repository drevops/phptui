<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Field;

use DrevOps\PhpTui\Field\AbstractField;
use DrevOps\PhpTui\Field\Capability\CompletionCapableTrait;
use DrevOps\PhpTui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\PhpTui\Field\Capability\TextEditCapableTrait;
use DrevOps\PhpTui\Field\Text;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Testing\ArrayKeyStream;
use DrevOps\PhpTui\Testing\FieldRunner;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the text field.
 */
#[CoversClass(Text::class)]
#[CoversClass(AbstractField::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(CompletionCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[CoversClass(FieldRunner::class)]
#[Group('field')]
final class TextTest extends TestCase {

  public function testTypesAndAccepts(): void {
    $field = new Text();

    $value = FieldRunner::run($field, ArrayKeyStream::of('Acme', Key::named(KeyName::Enter)));

    $this->assertSame('Acme', $value);
    $this->assertTrue($field->isComplete());
  }

  public function testRefusalIsShownUnderTheBuffer(): void {
    $field = new Text('');

    $field->refused('Required.');

    $this->assertSame('Required.', $field->error());
    $this->assertStringContainsString('Required.', $field->view(new DefaultTheme()));

    // Offering again clears what was said about the last offer.
    $field->handle(Key::char('a'));
    $field->handle(Key::char('b'));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertNull($field->error());
    $this->assertTrue($field->isComplete());
    $this->assertSame('ab', $field->value());
  }

  public function testCursorEditingAndBackspace(): void {
    $field = new Text('ac');

    $field->handle(Key::named(KeyName::Left));
    $field->handle(Key::char('b'));
    $this->assertSame('abc', $field->value());

    $field->handle(Key::named(KeyName::Backspace));
    $this->assertSame('ac', $field->value());

    $field->handle(Key::named(KeyName::Right));
    $this->assertStringContainsString('█', $field->view(new DefaultTheme()));
  }

  public function testMultibyteEditingKeepsCharacterBoundaries(): void {
    $field = new Text();

    // One Backspace removes a whole multi-byte character, not one byte.
    $field->handle(Key::char('é'));
    $field->handle(Key::char('x'));
    $field->handle(Key::named(KeyName::Backspace));
    $field->handle(Key::named(KeyName::Backspace));
    $this->assertSame('', $field->value());

    // Left moves over a whole character, so an insertion cannot split it.
    $field->handle(Key::char('é'));
    $field->handle(Key::named(KeyName::Left));
    $field->handle(Key::char('a'));
    $this->assertSame('aé', $field->value());
  }

  public function testBufferExposesTheLiveInput(): void {
    $field = new Text('ab');

    $field->handle(Key::char('c'));

    $this->assertSame('abc', $field->buffer());
  }

  public function testCancel(): void {
    $field = new Text('x');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
    $this->assertNull($value);
  }

  public function testSpaceInsertsSpace(): void {
    $field = new Text();

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Space), Key::char('b'), Key::named(KeyName::Enter)));

    $this->assertSame('a b', $value);
  }

  public function testHints(): void {
    // A plain field contributes the shared accept/cancel hints.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Text())->hints());

    $this->assertSame(['accept', 'cancel'], $labels);
  }

  public function testGhostTextRendersDimmedSuffix(): void {
    // The first candidate is skipped (no prefix match); the second completes.
    $field = new Text('', ['other', 'acme-site']);

    $field->handle(Key::char('a'));
    $field->handle(Key::char('c'));

    // The typed prefix stays put and the remaining suffix is dimmed (SGR 90).
    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('me-site', $view);
    $this->assertStringContainsString("\033[90m", $view);

    // The ghost is a preview: the value stays the typed text until accepted.
    $this->assertSame('ac', $field->value());
  }

  public function testTabAcceptsCompletion(): void {
    $field = new Text('', ['acme-site']);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    $this->assertSame('acme-site', $value);
  }

  public function testRightAtEndAcceptsCompletion(): void {
    $field = new Text('', ['acme-site']);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame('acme-site', $value);
  }

  public function testRightMidBufferMovesCaretWithoutCompleting(): void {
    $field = new Text('ab', ['abcdef']);

    // With the caret off the end there is no ghost, so Right advances the caret
    // rather than accepting a completion.
    $field->handle(Key::named(KeyName::Left));
    $field->handle(Key::named(KeyName::Right));

    $this->assertSame('ab', $field->value());
  }

  public function testCaseInsensitiveMatchCanonicalisesOnAccept(): void {
    $field = new Text('', ['GitHub']);

    $field->handle(Key::char('g'));
    $field->handle(Key::char('i'));
    $field->handle(Key::named(KeyName::Tab));

    // A lower-case prefix matches and accepting adopts the candidate's case.
    $this->assertSame('GitHub', $field->value());
  }

  public function testGhostTextIsUnicodeAware(): void {
    // strtolower() folds only ASCII, so a non-ASCII prefix must fold with
    // mbstring; the multibyte suffix must render whole, not split mid-byte.
    $field = new Text('', ['Éclair']);

    $field->handle(Key::char('é'));
    $this->assertStringContainsString('clair', $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('Éclair', $field->value());
  }

  public function testNoMatchLeavesPlainField(): void {
    $field = new Text('', ['acme-site']);

    $field->handle(Key::char('z'));

    // No candidate starts with "z": no dimmed ghost, and Tab is inert.
    $view = $field->view(new DefaultTheme());
    $this->assertStringNotContainsString("\033[90m", $view);

    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('z', $field->value());
  }

  public function testFullyTypedCandidateHasNoGhost(): void {
    $field = new Text('', ['php']);

    $field->handle(Key::char('p'));
    $field->handle(Key::char('h'));
    $field->handle(Key::char('p'));

    // The buffer already equals the only candidate; nothing is left to ghost.
    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));
  }

  public function testEmptyBufferShowsNoGhost(): void {
    // With nothing typed there is no prefix to complete, so no ghost renders.
    $field = new Text('', ['acme-site']);

    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));
  }

  public function testGhostSuppressedInNoAnsiMode(): void {
    $field = new Text('', ['acme-site']);

    $field->handle(Key::char('a'));
    $field->handle(Key::char('c'));

    // Without colour the ghost cannot be dimmed, so it is suppressed and no
    // escape sequences leak into the plain-text line.
    $view = $field->view(new DefaultTheme(76, ['color' => FALSE]));
    $this->assertStringNotContainsString('me-site', $view);
    $this->assertStringNotContainsString("\033", $view);
  }

  public function testPlaceholderGhostsAnEmptyBuffer(): void {
    $field = (new Text())->setPlaceholder('E.g. Golden Beetroot');

    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('E.g. Golden Beetroot', $view);
    $this->assertStringContainsString("\033[90m", $view);

    // The placeholder is not a value: the field still reads as unanswered.
    $this->assertSame('', $field->value());
  }

  public function testPlaceholderClearsOnFirstKeystroke(): void {
    $field = (new Text())->setPlaceholder('E.g. Golden Beetroot');

    $field->handle(Key::char('a'));

    $this->assertStringNotContainsString('E.g. Golden Beetroot', $field->view(new DefaultTheme()));
  }

  public function testPlaceholderNeverCompetesWithCompletion(): void {
    $field = (new Text('', ['acme-site']))->setPlaceholder('E.g. Golden Beetroot');

    // A completion needs a typed prefix and a placeholder needs an empty
    // buffer, so the one ghost slot is never contested.
    $field->handle(Key::char('a'));

    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('cme-site', $view);
    $this->assertStringNotContainsString('E.g. Golden Beetroot', $view);
  }

  public function testPlaceholderSuppressedInNoAnsiMode(): void {
    $field = (new Text())->setPlaceholder('E.g. Golden Beetroot');

    // Without colour it would read as a typed value rather than as a prompt.
    $this->assertStringNotContainsString('E.g. Golden Beetroot', $field->view(new DefaultTheme(76, ['color' => FALSE])));
  }

  public function testUndeclaredPlaceholderGhostsNothing(): void {
    $this->assertStringNotContainsString("\033[90m", (new Text())->view(new DefaultTheme()));
  }

}
