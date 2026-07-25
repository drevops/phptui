<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's semantic styler and symbol methods.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('tui')]
final class ThemeTest extends TestCase {

  #[DataProvider('dataProviderStyler')]
  public function testStyler(\Closure $styled, string $code): void {
    $this->assertSame(Ansi::style('X', $code), $styled());
  }

  public static function dataProviderStyler(): \Iterator {
    // The default theme colours these per mode.
    yield 'dark title' => [static fn(): string => (new DefaultTheme())->title('X'), '1;36'];
    yield 'dark value' => [static fn(): string => (new DefaultTheme())->value('X'), '32'];
    yield 'dark indicator' => [static fn(): string => (new DefaultTheme())->indicator('X'), '1;33'];
    yield 'dark border' => [static fn(): string => (new DefaultTheme())->border('X'), '36'];
    yield 'dark match highlight' => [static fn(): string => (new DefaultTheme())->highlightMatch('X'), '1;33'];
    yield 'light title' => [static fn(): string => self::light()->title('X'), '1;34'];
    yield 'light indicator' => [static fn(): string => self::light()->indicator('X'), '35'];
    yield 'light border' => [static fn(): string => self::light()->border('X'), '34'];
    yield 'light match highlight' => [static fn(): string => self::light()->highlightMatch('X'), '1;35'];
    // These roles are mode-independent: dimmed chrome and the red error.
    yield 'description' => [static fn(): string => (new DefaultTheme())->description('X'), '90'];
    yield 'error' => [static fn(): string => (new DefaultTheme())->error('X'), '31'];
    yield 'breadcrumb' => [static fn(): string => self::light()->breadcrumb('X'), '90'];
    // Option-list roles: a bold-gray heading and a gray disabled option.
    yield 'heading' => [static fn(): string => (new DefaultTheme())->heading('X'), '1;90'];
    yield 'disabled' => [static fn(): string => (new DefaultTheme())->disabled('X'), '90'];
    // Inline ghost-text is dimmed gray, the same as the other dimmed chrome.
    yield 'ghost' => [static fn(): string => (new DefaultTheme())->ghost('X'), '90'];
    // Markdown spans map to bold, italic and a mode-specific code colour.
    yield 'strong' => [static fn(): string => (new DefaultTheme())->strong('X'), '1'];
    yield 'emphasis' => [static fn(): string => (new DefaultTheme())->emphasis('X'), '3'];
    yield 'dark code' => [static fn(): string => (new DefaultTheme())->code('X'), '93'];
    yield 'light code' => [static fn(): string => self::light()->code('X'), '35'];
  }

  public function testBulletGlyph(): void {
    $this->assertSame('•', (new DefaultTheme())->bullet());
    $this->assertSame('-', (new DefaultTheme(76, ['unicode' => FALSE]))->bullet());
  }

  public function testLinkAtomEmitsHyperlinkWithColour(): void {
    $link = (new DefaultTheme())->link('Orchard', 'https://example.com/orchard');

    $this->assertSame(Ansi::link('https://example.com/orchard', 'Orchard'), $link);
    $this->assertSame('Orchard', Ansi::strip($link));
  }

  public function testLinkAtomDegradesWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('Orchard (https://example.com/orchard)', $theme->link('Orchard', 'https://example.com/orchard'));
  }

  public function testLabelResolvesLinks(): void {
    $theme = new DefaultTheme();
    $label = $theme->label('open [Basket](https://example.com/basket)');

    // The label keeps its styling and the link is clickable inside it.
    $this->assertStringContainsString(Ansi::link('https://example.com/basket', 'Basket'), $label);
    $this->assertSame('open Basket', Ansi::strip($label));
  }

  public function testLabelLinkDegradesWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('open Basket (https://example.com/basket)', $theme->label('open [Basket](https://example.com/basket)'));
  }

  public function testDescriptionBlockRendersMarkdownWhenEnabled(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE]);
    $lines = $theme->renderDescriptionBlock('pack **ripe** *sweet* `pears`', FALSE);

    $this->assertCount(1, $lines);
    $this->assertSame('    pack ripe sweet pears', Ansi::strip($lines[0]));
    // Bold, italic and the code colour each carry their own SGR.
    $this->assertStringContainsString("\033[1mripe\033[0m", $lines[0]);
    $this->assertStringContainsString("\033[3msweet\033[0m", $lines[0]);
    $this->assertStringContainsString("\033[93mpears\033[0m", $lines[0]);
  }

  public function testDescriptionBlockRendersBulletList(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE]);
    $lines = $theme->renderDescriptionBlock("- apples\n- pears", FALSE);

    $this->assertCount(2, $lines);
    $this->assertSame('    • apples', Ansi::strip($lines[0]));
    $this->assertSame('    • pears', Ansi::strip($lines[1]));
  }

  public function testDescriptionBlockLeavesMarkdownLiteralWhenDisabled(): void {
    $theme = new DefaultTheme();
    $lines = $theme->renderDescriptionBlock('pack **ripe** pears', FALSE);

    // With markdown off the markers stay literal, but links still resolve.
    $this->assertCount(1, $lines);
    $this->assertSame('    pack **ripe** pears', Ansi::strip($lines[0]));
  }

  public function testDescriptionBlockStripsToCleanTextWithoutColour(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE, 'color' => FALSE]);
    $lines = $theme->renderDescriptionBlock("Pick **ripe** `pears` [here](https://example.com/here):\n- gala\n- bosc", FALSE);

    // Markdown enabled but no colour: markers drop, links degrade, bullets show
    // as plain glyphs, and not a single escape sequence survives.
    $joined = implode("\n", $lines);
    $this->assertSame($joined, Ansi::strip($joined));
    $this->assertStringContainsString('Pick ripe pears here (https://example.com/here):', $joined);
    $this->assertStringContainsString('    • gala', $joined);
    $this->assertStringContainsString('    • bosc', $joined);
  }

  public function testDescriptionBlockResolvesLinksWithoutMarkdown(): void {
    $theme = new DefaultTheme();
    $lines = $theme->renderDescriptionBlock('see [Guide](https://example.com/guide)', FALSE);

    $this->assertSame('    see Guide', Ansi::strip($lines[0]));
    $this->assertStringContainsString(Ansi::link('https://example.com/guide', 'Guide'), $lines[0]);
  }

  public function testGhostSuppressedWithoutColour(): void {
    // Ghost-text cannot be dimmed without ANSI, so it is suppressed entirely
    // rather than rendered as indistinguishable plain text.
    $this->assertSame('', (new DefaultTheme(76, ['color' => FALSE]))->ghost('X'));
    $this->assertStringContainsString("\033[90m", (new DefaultTheme())->ghost('X'));
  }

  public function testDivider(): void {
    $this->assertSame('──────────', (new DefaultTheme(10, ['color' => FALSE, 'border' => Border::None]))->divider());
    $this->assertSame('----------', (new DefaultTheme(10, ['unicode' => FALSE, 'color' => FALSE, 'border' => Border::None]))->divider());
    // The divider is dimmed when colour is on.
    $this->assertStringContainsString("\033[90m", (new DefaultTheme(10))->divider());
  }

  public function testSelectedRowIsBold(): void {
    $theme = new DefaultTheme();

    // A row styler bolds its text when selected.
    $this->assertSame(Ansi::style('X', '1;32'), $theme->value('X', TRUE));
    $this->assertStringContainsString("\033[1", $theme->label('X', TRUE));
    $this->assertStringNotContainsString("\033[1", $theme->label('X', FALSE));
  }

  public function testColourOffLeavesTextPlain(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('Setup', $theme->title('Setup'));
    $this->assertSame('X', $theme->value('X', TRUE));
    $this->assertFalse($theme->hasColor());
  }

  #[DataProvider('dataProviderGlyph')]
  public function testGlyph(bool $unicode, \Closure $glyph, string $expected): void {
    $theme = new DefaultTheme(76, ['unicode' => $unicode, 'color' => FALSE]);

    $this->assertSame($expected, $glyph($theme));
  }

  public static function dataProviderGlyph(): \Iterator {
    yield 'unicode arrow' => [TRUE, static fn(DefaultTheme $t): string => $t->arrow(), '›'];
    yield 'ascii arrow' => [FALSE, static fn(DefaultTheme $t): string => $t->arrow(), '>'];
    yield 'unicode enter' => [TRUE, static fn(DefaultTheme $t): string => $t->enter(), '↵'];
    yield 'unicode dot' => [TRUE, static fn(DefaultTheme $t): string => $t->dot(), '·'];
    yield 'unicode caret' => [TRUE, static fn(DefaultTheme $t): string => $t->caret(), '█'];
    yield 'ascii caret' => [FALSE, static fn(DefaultTheme $t): string => $t->caret(), '|'];
    yield 'unicode mask' => [TRUE, static fn(DefaultTheme $t): string => $t->mask(), '•'];
    yield 'unicode indicator up' => [TRUE, static fn(DefaultTheme $t): string => $t->indicatorUp(), '▲'];
  }

  public function testMarkerRadioCheck(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('❯', $theme->marker(TRUE));
    $this->assertSame(' ', $theme->marker(FALSE));
    $this->assertSame('●', $theme->radio(TRUE));
    $this->assertSame('○', $theme->radio(FALSE));
    $this->assertSame('◼', $theme->check(TRUE));
    $this->assertSame('◻', $theme->check(FALSE));

    $ascii = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);
    $this->assertSame('>', $ascii->marker(TRUE));
    $this->assertSame('(*)', $ascii->radio(TRUE));
    $this->assertSame('[ ]', $ascii->check(FALSE));
  }

  public function testCursorAccentIsShared(): void {
    // The marker, caret and radio all carry the cursor accent, per mode.
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->marker(TRUE));
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->caret());
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->radio(TRUE));
    $this->assertStringContainsString("\033[1;34m", self::light()->marker(TRUE));
  }

  public function testCustomThemeOverridesOneElement(): void {
    $theme = new class() extends DefaultTheme {

      #[\Override]
      public function title(string $text): string {
        return $this->paint('1;95', $text);
      }

    };

    // The overridden element changes; everything else stays the default.
    $this->assertSame(Ansi::style('X', '1;95'), $theme->title('X'));
    $this->assertSame(Ansi::style('X', '32'), $theme->value('X'));
  }

  public function testHasUnicode(): void {
    $this->assertTrue((new DefaultTheme())->hasUnicode());
    $this->assertFalse((new DefaultTheme(76, ['unicode' => FALSE]))->hasUnicode());
  }

  public function testDefaultThemePaintsNoBackground(): void {
    $this->assertNull((new DefaultTheme())->background());
  }

  /**
   * A default theme in light mode.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected static function light(): DefaultTheme {
    return new DefaultTheme(76, ['mode' => Mode::Light]);
  }

}
