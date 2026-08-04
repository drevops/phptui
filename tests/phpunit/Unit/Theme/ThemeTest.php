<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Block\Prose;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests what each of the theme's elements paints and which glyph it draws.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(Prose::class)]
#[Group('theme')]
final class ThemeTest extends TestCase {

  #[DataProvider('dataProviderElementPaint')]
  public function testElementPaint(\Closure $styled, string $code): void {
    $this->assertSame(Ansi::style('X', $code), $styled());
  }

  public static function dataProviderElementPaint(): \Iterator {
    // The default theme colours these per mode.
    yield 'dark title' => [static fn(): string => (new DefaultTheme())->markupTitle('X'), '1;36'];
    yield 'dark value' => [static fn(): string => (new DefaultTheme())->fieldValue('X'), '32'];
    yield 'dark border' => [static fn(): string => (new DefaultTheme())->chromeBorder('X'), '36'];
    yield 'dark match' => [static fn(): string => (new DefaultTheme())->fieldEntryMatch('X'), '1;33'];
    yield 'light title' => [static fn(): string => self::light()->markupTitle('X'), '1;34'];
    yield 'light border' => [static fn(): string => self::light()->chromeBorder('X'), '34'];
    yield 'light match' => [static fn(): string => self::light()->fieldEntryMatch('X'), '1;35'];
    // These roles are mode-independent: dimmed chrome and the red error.
    yield 'description' => [static fn(): string => (new DefaultTheme())->fieldDescription('X'), '90'];
    // Guidance on how to answer steps along the grey ramp rather than taking a
    // hue: it is drawn beside a description and must not be mistaken for one,
    // but a coloured guidance line would read as output rather than as chrome.
    yield 'constraint' => [static fn(): string => (new DefaultTheme())->fieldConstraint('X'), '3;38;5;246'];
    yield 'error' => [static fn(): string => (new DefaultTheme())->fieldError('X'), '31'];
    yield 'breadcrumb' => [static fn(): string => self::light()->breadcrumbLabel('X'), '90'];
    yield 'entry note' => [static fn(): string => (new DefaultTheme())->fieldEntryNote('X'), '90'];
    yield 'state' => [static fn(): string => (new DefaultTheme())->fieldState('X'), '90'];
    yield 'caption' => [static fn(): string => (new DefaultTheme())->fieldCaption('X'), '1;38;5;109'];
    // Inline ghost-text is dimmed gray, the same as the other dimmed chrome.
    yield 'ghost' => [static fn(): string => (new DefaultTheme())->fieldGhost('X'), '90'];
    // Markdown spans map to bold, italic and a mode-specific code colour.
    yield 'strong' => [static fn(): string => (new DefaultTheme())->markupStrong('X'), '1'];
    yield 'emphasis' => [static fn(): string => (new DefaultTheme())->markupEmphasis('X'), '3'];
    yield 'dark code' => [static fn(): string => (new DefaultTheme())->markupCode('X'), '93'];
    yield 'light code' => [static fn(): string => self::light()->markupCode('X'), '35'];
  }

  public function testOverflowMarkerCarriesTheAttentionHue(): void {
    $this->assertSame(Ansi::style('▲', '1;33'), (new DefaultTheme())->chromeOverflowMarker(TRUE));
    $this->assertSame(Ansi::style('▼', '35'), self::light()->chromeOverflowMarker(FALSE));
  }

  public function testBulletGlyph(): void {
    $this->assertSame('•', (new DefaultTheme())->markupBullet());
    $this->assertSame('-', (new DefaultTheme(76, ['unicode' => FALSE]))->markupBullet());
  }

  public function testLinkElementEmitsHyperlinkWithColour(): void {
    $link = (new DefaultTheme())->markupLink('Orchard', 'https://example.com/orchard');

    $this->assertSame(Ansi::link('Orchard', 'https://example.com/orchard'), $link);
    $this->assertSame('Orchard', Ansi::strip($link));
  }

  public function testLinkElementDegradesWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('Orchard (https://example.com/orchard)', $theme->markupLink('Orchard', 'https://example.com/orchard'));
  }

  public function testLabelResolvesLinks(): void {
    $theme = new DefaultTheme();
    $label = $theme->fieldLabel('open [Basket](https://example.com/basket)');

    // The label keeps its styling and the link is clickable inside it.
    $this->assertStringContainsString(Ansi::link('Basket', 'https://example.com/basket'), $label);
    $this->assertSame('open Basket', Ansi::strip($label));
  }

  public function testLabelLinkDegradesWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('open Basket (https://example.com/basket)', $theme->fieldLabel('open [Basket](https://example.com/basket)'));
  }

  public function testProseRendersMarkdownWhenEnabled(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE]);
    $lines = Prose::lines('pack **ripe** *sweet* `pears`', $theme);

    $this->assertCount(1, $lines);
    $this->assertSame('pack ripe sweet pears', Ansi::strip($lines[0]));
    // Bold, italic and the code colour each carry their own SGR.
    $this->assertStringContainsString("\033[1mripe\033[0m", $lines[0]);
    $this->assertStringContainsString("\033[3msweet\033[0m", $lines[0]);
    $this->assertStringContainsString("\033[93mpears\033[0m", $lines[0]);
  }

  public function testProseRendersBulletList(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE]);
    $lines = Prose::lines("- apples\n- pears", $theme);

    $this->assertCount(2, $lines);
    $this->assertSame('• apples', Ansi::strip($lines[0]));
    $this->assertSame('• pears', Ansi::strip($lines[1]));
  }

  public function testProseLeavesMarkdownLiteralWhenDisabled(): void {
    $theme = new DefaultTheme();
    $lines = Prose::lines('pack **ripe** pears', $theme);

    // With markdown off the markers stay literal, but links still resolve.
    $this->assertCount(1, $lines);
    $this->assertSame('pack **ripe** pears', Ansi::strip($lines[0]));
  }

  public function testProseStripsToCleanTextWithoutColour(): void {
    $theme = new DefaultTheme(76, ['markdown' => TRUE, 'color' => FALSE]);
    $lines = Prose::lines("Pick **ripe** `pears` [here](https://example.com/here):\n- gala\n- bosc", $theme);

    // Markdown enabled but no colour: markers drop, links degrade, bullets show
    // as plain glyphs, and not a single escape sequence survives.
    $joined = implode("\n", $lines);
    $this->assertSame($joined, Ansi::strip($joined));
    $this->assertStringContainsString('Pick ripe pears here (https://example.com/here):', $joined);
    $this->assertStringContainsString('• gala', $joined);
    $this->assertStringContainsString('• bosc', $joined);
  }

  public function testProseNormalizesLineEndings(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE, 'markdown' => TRUE]);
    $lines = Prose::lines("- apples\r\n- pears\r- plums", $theme);

    // CRLF and lone CR both split into their own physical lines, with no stray
    // carriage return left to overprint the row.
    $this->assertSame(['• apples', '• pears', '• plums'], $lines);
  }

  public function testProseResolvesLinksWithoutMarkdown(): void {
    $theme = new DefaultTheme();
    $lines = Prose::lines('see [Guide](https://example.com/guide)', $theme);

    $this->assertSame('see Guide', Ansi::strip($lines[0]));
    $this->assertStringContainsString(Ansi::link('Guide', 'https://example.com/guide'), $lines[0]);
  }

  public function testGhostSuppressedWithoutColour(): void {
    // Ghost-text cannot be dimmed without ANSI, so it is suppressed entirely
    // rather than rendered as indistinguishable plain text.
    $this->assertSame('', (new DefaultTheme(76, ['color' => FALSE]))->fieldGhost('X'));
    $this->assertStringContainsString("\033[90m", (new DefaultTheme())->fieldGhost('X'));
  }

  public function testRule(): void {
    $this->assertSame('──────────', (new DefaultTheme(10, ['color' => FALSE, 'border' => Border::None]))->renderRule());
    $this->assertSame('----------', (new DefaultTheme(10, ['unicode' => FALSE, 'color' => FALSE, 'border' => Border::None]))->renderRule());
    // The rule is dimmed when colour is on.
    $this->assertStringContainsString("\033[90m", (new DefaultTheme(10))->renderRule());
    // One rule wherever it appears: what stands between two runs of entries is
    // what stands between two blocks of standalone output.
    $this->assertSame((new DefaultTheme(10))->renderRule(), (new DefaultTheme(10))->fieldEntrySeparator());
  }

  public function testPickedEntryTakesWeightAndFocusTakesTheAccent(): void {
    $theme = new DefaultTheme();

    $this->assertStringContainsString("\033[1", $theme->fieldEntry('X', TRUE));
    $this->assertStringNotContainsString("\033[1", $theme->fieldEntry('X', FALSE));
    $this->assertSame(Ansi::style('X', '1;36'), $theme->fieldEntry('X', FALSE, TRUE));
  }

  public function testColourOffLeavesTextPlain(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('Setup', $theme->markupTitle('Setup'));
    $this->assertSame('X', $theme->fieldValue('X'));
    $this->assertFalse($theme->hasColor());
  }

  #[DataProvider('dataProviderGlyph')]
  public function testGlyph(bool $unicode, \Closure $glyph, string $expected): void {
    $theme = new DefaultTheme(76, ['unicode' => $unicode, 'color' => FALSE]);

    $this->assertSame($expected, $glyph($theme));
  }

  public static function dataProviderGlyph(): \Iterator {
    yield 'unicode descend' => [TRUE, static fn(DefaultTheme $t): string => $t->panelDescend(), '›'];
    yield 'ascii descend' => [FALSE, static fn(DefaultTheme $t): string => $t->panelDescend(), '>'];
    yield 'unicode breadcrumb separator' => [TRUE, static fn(DefaultTheme $t): string => $t->breadcrumbSeparator(), '›'];
    yield 'unicode enter' => [TRUE, static fn(DefaultTheme $t): string => $t->keyGlyph(KeyName::Enter), '↵'];
    yield 'unicode summary separator' => [TRUE, static fn(DefaultTheme $t): string => $t->panelSummarySeparator(), '·'];
    yield 'unicode caret' => [TRUE, static fn(DefaultTheme $t): string => $t->fieldCaret(), '█'];
    yield 'ascii caret' => [FALSE, static fn(DefaultTheme $t): string => $t->fieldCaret(), '|'];
    yield 'unicode mask' => [TRUE, static fn(DefaultTheme $t): string => $t->fieldMask(), '•'];
    yield 'unicode overflow marker' => [TRUE, static fn(DefaultTheme $t): string => $t->chromeOverflowMarker(TRUE), '▲'];
    yield 'ascii overflow marker' => [FALSE, static fn(DefaultTheme $t): string => $t->chromeOverflowMarker(TRUE), '^'];
    yield 'unicode field overflow marker' => [TRUE, static fn(DefaultTheme $t): string => $t->fieldOverflowMarker(FALSE), '▼'];
    yield 'ascii field overflow marker' => [FALSE, static fn(DefaultTheme $t): string => $t->fieldOverflowMarker(FALSE), 'v'];
  }

  public function testSelectorAndMarkerGlyphs(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);

    $this->assertSame('❯', $theme->fieldSelector(TRUE));
    $this->assertSame(' ', $theme->fieldSelector(FALSE));
    // A round mark for a question that takes one answer, a square one for a
    // question that takes several.
    $this->assertSame('●', $theme->fieldEntryMarker(TRUE, TRUE));
    $this->assertSame('○', $theme->fieldEntryMarker(FALSE, TRUE));
    $this->assertSame('◼', $theme->fieldEntryMarker(TRUE));
    $this->assertSame('◻', $theme->fieldEntryMarker(FALSE));

    $ascii = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);
    $this->assertSame('>', $ascii->fieldSelector(TRUE));
    $this->assertSame('(*)', $ascii->fieldEntryMarker(TRUE, TRUE));
    $this->assertSame('[ ]', $ascii->fieldEntryMarker(FALSE));
  }

  public function testCursorAccentIsShared(): void {
    // The selector, caret and exclusive mark all carry the cursor accent.
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->fieldSelector(TRUE));
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->fieldCaret());
    $this->assertStringContainsString("\033[1;36m", (new DefaultTheme())->fieldEntryMarker(TRUE, TRUE));
    $this->assertStringContainsString("\033[1;34m", self::light()->fieldSelector(TRUE));
  }

  public function testCustomThemeRepaintsOneHue(): void {
    $theme = new class() extends DefaultTheme {

      #[\Override]
      protected function accent(): string {
        return '1;95';
      }

    };

    // Everything drawn from the accent follows; everything else stays default.
    $this->assertSame(Ansi::style('X', '1;95'), $theme->markupTitle('X'));
    $this->assertSame(Ansi::style('X', '1;95'), $theme->fieldEntry('X', FALSE, TRUE));
    $this->assertSame(Ansi::style('X', '32'), $theme->fieldValue('X'));
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
