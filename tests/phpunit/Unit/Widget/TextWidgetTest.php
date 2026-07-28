<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\Capability\CompletionCapableTrait;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;
use DrevOps\Tui\Widget\TextWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the text widget.
 */
#[CoversClass(TextWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversTrait(TextEditCapableTrait::class)]
#[CoversTrait(CompletionCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[CoversClass(WidgetRunner::class)]
#[Group('widget')]
final class TextWidgetTest extends TestCase {

  public function testTypesAndAccepts(): void {
    $widget = new TextWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('Acme', Key::named(KeyName::Enter)));

    $this->assertSame('Acme', $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testTransformApplied(): void {
    $widget = (new TextWidget(''))->setHandlers(transform: fn(mixed $value): string => is_string($value) ? strtoupper($value) : '');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('acme', Key::named(KeyName::Enter)));

    $this->assertSame('ACME', $value);
  }

  public function testValidationBlocksThenAccepts(): void {
    $validate = fn(mixed $value): ?string => is_string($value) && $value !== '' ? NULL : 'Required.';
    $widget = (new TextWidget(''))->setHandlers($validate);

    $widget->handle(Key::named(KeyName::Enter));
    $this->assertFalse($widget->isComplete());
    $this->assertSame('Required.', $widget->error());
    $this->assertStringContainsString('Required.', $widget->view(new DefaultTheme()));

    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('b'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertNull($widget->error());
    $this->assertSame('ab', $widget->value());
  }

  public function testCursorEditingAndBackspace(): void {
    $widget = new TextWidget('ac');

    $widget->handle(Key::named(KeyName::Left));
    $widget->handle(Key::char('b'));
    $this->assertSame('abc', $widget->value());

    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertSame('ac', $widget->value());

    $widget->handle(Key::named(KeyName::Right));
    $this->assertStringContainsString('█', $widget->view(new DefaultTheme()));
  }

  public function testMultibyteEditingKeepsCharacterBoundaries(): void {
    $widget = new TextWidget();

    // One Backspace removes a whole multi-byte character, not one byte.
    $widget->handle(Key::char('é'));
    $widget->handle(Key::char('x'));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertSame('', $widget->value());

    // Left moves over a whole character, so an insertion cannot split it.
    $widget->handle(Key::char('é'));
    $widget->handle(Key::named(KeyName::Left));
    $widget->handle(Key::char('a'));
    $this->assertSame('aé', $widget->value());
  }

  public function testBufferExposesTheLiveInput(): void {
    $widget = new TextWidget('ab');

    $widget->handle(Key::char('c'));

    $this->assertSame('abc', $widget->buffer());
  }

  public function testCancel(): void {
    $widget = new TextWidget('x');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
    $this->assertNull($value);
  }

  public function testSpaceInsertsSpace(): void {
    $widget = new TextWidget();

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Space), Key::char('b'), Key::named(KeyName::Enter)));

    $this->assertSame('a b', $value);
  }

  public function testHints(): void {
    // A plain widget contributes the shared accept/cancel hints.
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new TextWidget())->hints());

    $this->assertSame(['accept', 'cancel'], $labels);
  }

  public function testGhostTextRendersDimmedSuffix(): void {
    // The first candidate is skipped (no prefix match); the second completes.
    $widget = new TextWidget('', ['other', 'acme-site']);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('c'));

    // The typed prefix stays put and the remaining suffix is dimmed (SGR 90).
    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('me-site', $view);
    $this->assertStringContainsString("\033[90m", $view);

    // The ghost is a preview: the value stays the typed text until accepted.
    $this->assertSame('ac', $widget->value());
  }

  public function testTabAcceptsCompletion(): void {
    $widget = new TextWidget('', ['acme-site']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    $this->assertSame('acme-site', $value);
  }

  public function testRightAtEndAcceptsCompletion(): void {
    $widget = new TextWidget('', ['acme-site']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::char('a'), Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame('acme-site', $value);
  }

  public function testRightMidBufferMovesCaretWithoutCompleting(): void {
    $widget = new TextWidget('ab', ['abcdef']);

    // With the caret off the end there is no ghost, so Right advances the caret
    // rather than accepting a completion.
    $widget->handle(Key::named(KeyName::Left));
    $widget->handle(Key::named(KeyName::Right));

    $this->assertSame('ab', $widget->value());
  }

  public function testCaseInsensitiveMatchCanonicalisesOnAccept(): void {
    $widget = new TextWidget('', ['GitHub']);

    $widget->handle(Key::char('g'));
    $widget->handle(Key::char('i'));
    $widget->handle(Key::named(KeyName::Tab));

    // A lower-case prefix matches and accepting adopts the candidate's case.
    $this->assertSame('GitHub', $widget->value());
  }

  public function testGhostTextIsUnicodeAware(): void {
    // strtolower() folds only ASCII, so a non-ASCII prefix must fold with
    // mbstring; the multibyte suffix must render whole, not split mid-byte.
    $widget = new TextWidget('', ['Éclair']);

    $widget->handle(Key::char('é'));
    $this->assertStringContainsString('clair', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame('Éclair', $widget->value());
  }

  public function testNoMatchLeavesPlainField(): void {
    $widget = new TextWidget('', ['acme-site']);

    $widget->handle(Key::char('z'));

    // No candidate starts with "z": no dimmed ghost, and Tab is inert.
    $view = $widget->view(new DefaultTheme());
    $this->assertStringNotContainsString("\033[90m", $view);

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame('z', $widget->value());
  }

  public function testFullyTypedCandidateHasNoGhost(): void {
    $widget = new TextWidget('', ['php']);

    $widget->handle(Key::char('p'));
    $widget->handle(Key::char('h'));
    $widget->handle(Key::char('p'));

    // The buffer already equals the only candidate; nothing is left to ghost.
    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));
  }

  public function testEmptyBufferShowsNoGhost(): void {
    // With nothing typed there is no prefix to complete, so no ghost renders.
    $widget = new TextWidget('', ['acme-site']);

    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));
  }

  public function testGhostSuppressedInNoAnsiMode(): void {
    $widget = new TextWidget('', ['acme-site']);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('c'));

    // Without colour the ghost cannot be dimmed, so it is suppressed and no
    // escape sequences leak into the plain-text line.
    $view = $widget->view(new DefaultTheme(76, ['color' => FALSE]));
    $this->assertStringNotContainsString('me-site', $view);
    $this->assertStringNotContainsString("\033", $view);
  }

  public function testPlaceholderGhostsAnEmptyBuffer(): void {
    $widget = (new TextWidget())->setPlaceholder('E.g. Golden Beetroot');

    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('E.g. Golden Beetroot', $view);
    $this->assertStringContainsString("\033[90m", $view);

    // The placeholder is not a value: the field still reads as unanswered.
    $this->assertSame('', $widget->value());
  }

  public function testPlaceholderClearsOnFirstKeystroke(): void {
    $widget = (new TextWidget())->setPlaceholder('E.g. Golden Beetroot');

    $widget->handle(Key::char('a'));

    $this->assertStringNotContainsString('E.g. Golden Beetroot', $widget->view(new DefaultTheme()));
  }

  public function testPlaceholderNeverCompetesWithCompletion(): void {
    $widget = (new TextWidget('', ['acme-site']))->setPlaceholder('E.g. Golden Beetroot');

    // A completion needs a typed prefix and a placeholder needs an empty
    // buffer, so the one ghost slot is never contested.
    $widget->handle(Key::char('a'));

    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('cme-site', $view);
    $this->assertStringNotContainsString('E.g. Golden Beetroot', $view);
  }

  public function testPlaceholderSuppressedInNoAnsiMode(): void {
    $widget = (new TextWidget())->setPlaceholder('E.g. Golden Beetroot');

    // Without colour it would read as a typed value rather than as a prompt.
    $this->assertStringNotContainsString('E.g. Golden Beetroot', $widget->view(new DefaultTheme(76, ['color' => FALSE])));
  }

  public function testUndeclaredPlaceholderGhostsNothing(): void {
    $this->assertStringNotContainsString("\033[90m", (new TextWidget())->view(new DefaultTheme()));
  }

}
