<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\EmberTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme's standalone output rendering: box, status, definitions.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(EmberTheme::class)]
#[Group('tui')]
final class OutputRenderTest extends TestCase {

  public function testBoxDrawsTitledFrameSizedToItsContent(): void {
    $lines = $this->theme(color: FALSE)->renderBox('Welcome', ['Pick your fruit.']);

    $this->assertCount(4, $lines);
    $this->assertStringStartsWith('╭', $lines[0]);
    $this->assertStringContainsString('Welcome', $lines[1]);
    $this->assertStringContainsString('Pick your fruit.', $lines[2]);
    $this->assertStringStartsWith('╰', $lines[3]);

    // Every row is the same width, so the frame closes cleanly.
    $widths = array_map(Ansi::width(...), $lines);
    $this->assertSame([$widths[0]], array_unique($widths));
  }

  public function testBoxWithoutTitleDrawsTheBodyAlone(): void {
    $lines = $this->theme(color: FALSE)->renderBox('', ['Pick your fruit.']);

    $this->assertCount(3, $lines);
    $this->assertStringContainsString('Pick your fruit.', $lines[1]);
  }

  public function testBoxWithNeitherTitleNorBodyRendersNothing(): void {
    $this->assertSame([], $this->theme()->renderBox('', []));
  }

  public function testBoxWrapsLongBodyTextInsideTheBorder(): void {
    $long = 'The weekly produce box carries apples, pears, plums, carrots, beetroot and a bunch of fresh herbs picked the same morning.';
    $lines = $this->theme(color: FALSE)->renderBox('', [$long]);

    // The sentence flows onto more rows rather than being clipped, and every
    // row still fits the frame.
    $this->assertGreaterThan(3, count($lines));

    foreach ($lines as $line) {
      $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, Ansi::width($line));
    }

    $this->assertStringContainsString('apples', implode(' ', $lines));
    $this->assertStringContainsString('herbs', implode(' ', $lines));
  }

  public function testBoxKeepsBlankLinesAndSplitsEmbeddedNewlines(): void {
    $lines = $this->theme(color: FALSE)->renderBox('', ['Apples', '', "Pears\r\nPlums"]);

    // Four content rows between the two rules: the blank one is preserved and
    // the CRLF pair splits into two.
    $this->assertCount(6, $lines);
    $this->assertStringContainsString('Apples', $lines[1]);
    $this->assertSame('', trim($lines[2], '│ '));
    $this->assertStringContainsString('Pears', $lines[3]);
    $this->assertStringContainsString('Plums', $lines[4]);
  }

  public function testBoxFallsBackToAsciiGlyphs(): void {
    $lines = $this->theme(color: FALSE, unicode: FALSE)->renderBox('Welcome', ['Fruit']);

    $this->assertStringStartsWith('+', $lines[0]);
    $this->assertStringContainsString('|', $lines[1]);
    $this->assertStringNotContainsString('╭', implode('', $lines));
  }

  public function testBoxCarriesTheThemeBorderAndHeadingColour(): void {
    $lines = $this->theme()->renderBox('Welcome', ['Fruit']);

    // The default dark border is cyan and the heading is bold grey.
    $this->assertStringContainsString("\033[36m", $lines[0]);
    $this->assertStringContainsString("\033[1;90m", $lines[1]);
  }

  #[DataProvider('dataProviderStatusLeadsWithItsOwnGlyph')]
  public function testStatusLeadsWithItsOwnGlyph(Status $status, string $unicode, string $ascii): void {
    $this->assertSame($unicode . ' Ready', $this->theme(color: FALSE)->renderStatus($status, 'Ready'));
    $this->assertSame($ascii . ' Ready', $this->theme(color: FALSE, unicode: FALSE)->renderStatus($status, 'Ready'));
  }

  /**
   * Data provider.
   *
   * @return \Iterator<string, array{Status, string, string}>
   *   The status, its Unicode glyph and its ASCII glyph.
   */
  public static function dataProviderStatusLeadsWithItsOwnGlyph(): \Iterator {
    yield 'note' => [Status::Note, '•', '-'];
    yield 'info' => [Status::Info, '›', '>'];
    yield 'success' => [Status::Success, '✓', '+'];
    yield 'warning' => [Status::Warning, '!', '!'];
    yield 'error' => [Status::Error, '✗', 'x'];
  }

  public function testEveryStatusGlyphIsOneColumnWide(): void {
    $theme = $this->theme(color: FALSE);

    // A run of status lines only aligns while no glyph renders double-width; an
    // empty message leaves the glyph alone on the line to measure.
    foreach (Status::cases() as $status) {
      $this->assertSame(1, Ansi::width($theme->renderStatus($status, '')), $status->value);
    }
  }

  #[DataProvider('dataProviderStatusCarriesItsOwnColour')]
  public function testStatusCarriesItsOwnColour(Status $status, string $sgr): void {
    $this->assertStringContainsString($sgr, $this->theme()->renderStatus($status, 'Ready'));
  }

  /**
   * Data provider.
   *
   * @return \Iterator<string, array{Status, string}>
   *   The status and the SGR the default dark theme paints it with.
   */
  public static function dataProviderStatusCarriesItsOwnColour(): \Iterator {
    yield 'note is grey' => [Status::Note, "\033[90m"];
    yield 'info is the accent' => [Status::Info, "\033[1;36m"];
    yield 'success is green' => [Status::Success, "\033[32m"];
    yield 'warning is the indicator' => [Status::Warning, "\033[1;33m"];
    yield 'error is red' => [Status::Error, "\033[31m"];
  }

  public function testStatusFoldsLineBreaksAndTrimsAnEmptyMessage(): void {
    $theme = $this->theme(color: FALSE);

    $this->assertSame('✓ Packed the box and sealed it', $theme->renderStatus(Status::Success, "Packed the box\nand sealed it"));
    // With no message the glyph stands alone rather than trailing a space.
    $this->assertSame('✓', $theme->renderStatus(Status::Success, ''));
  }

  public function testStatusResolvesLinksInItsMessage(): void {
    $line = $this->theme(color: FALSE)->renderStatus(Status::Info, 'See [the orchard](https://example.com/orchard)');

    // Without colour a link degrades to its label and target in plain text.
    $this->assertStringContainsString('the orchard', $line);
    $this->assertStringContainsString('https://example.com/orchard', $line);
    $this->assertStringNotContainsString('](', $line);
  }

  public function testBuiltinThemeRendersStatusInItsOwnAccent(): void {
    // Ember's highlight is bold orange; an info line inherits it with no
    // per-theme override, exactly as the spinner does.
    $theme = ThemeManager::create('ember', DefaultTheme::DEFAULT_WIDTH, ['color' => TRUE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertStringContainsString("\033[1;38;5;208m", $theme->renderStatus(Status::Info, 'Packing'));
  }

  public function testDefinitionsAlignValuesInOneColumn(): void {
    $lines = $this->theme(color: FALSE)->renderDefinitions(['Jars' => '12', 'Fruit' => 'Apricot']);

    $this->assertSame(['  Jars   12', '  Fruit  Apricot'], $lines);
  }

  public function testDefinitionsWithNoPairsRenderNothing(): void {
    $this->assertSame([], $this->theme()->renderDefinitions([]));
  }

  public function testDefinitionsKeepLabelWhoseValueIsEmpty(): void {
    $this->assertSame(['  Jars  '], $this->theme(color: FALSE)->renderDefinitions(['Jars' => '']));
  }

  public function testDefinitionsAcceptNumericLabel(): void {
    // A numeric-string key arrives as an integer; it still renders as a label.
    $this->assertSame(['  2024  Apricot'], $this->theme(color: FALSE)->renderDefinitions(['2024' => 'Apricot']));
  }

  public function testDefinitionsFoldLineBreaksInLabelsAndValues(): void {
    $this->assertSame(['  Jars  12 sealed'], $this->theme(color: FALSE)->renderDefinitions(["Jars" => "12\nsealed"]));
  }

  public function testDefinitionsWrapLongValueUnderTheValueColumn(): void {
    $value = 'Apricot, plum, greengage, damson, mirabelle, quince, medlar, pear, apple and a handful of blackcurrants.';
    $lines = $this->theme(color: FALSE)->renderDefinitions(['Fruit' => $value]);

    $this->assertGreaterThan(1, count($lines));
    // The continuation aligns under the value, not under the label.
    $this->assertSame(strpos($lines[0], 'Apricot'), strspn($lines[1], ' '));

    foreach ($lines as $line) {
      $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, Ansi::width($line));
    }
  }

  public function testDefinitionsCapRunawayLabelAtHalfTheFrame(): void {
    $label = str_repeat('long', 40);
    $lines = $this->theme(color: FALSE)->renderDefinitions([$label => 'Apricot']);

    // The label is clipped to half the frame, so the value keeps its own room.
    $this->assertStringContainsString('Apricot', $lines[0]);
    $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, Ansi::width($lines[0]));
  }

  public function testDefinitionsStyleLabelsAndValuesApart(): void {
    $lines = $this->theme()->renderDefinitions(['Jars' => '12']);

    // The value carries the theme's value colour (default dark: green).
    $this->assertStringContainsString("\033[32m", $lines[0]);
    $this->assertStringContainsString('Jars', $lines[0]);
  }

  /**
   * A default theme in the given display modes, fixed to dark.
   *
   * @param bool $color
   *   Whether colour is on.
   * @param bool $unicode
   *   Whether Unicode glyphs are on.
   *
   * @return \DrevOps\Tui\Theme\DefaultTheme
   *   The theme.
   */
  protected function theme(bool $color = TRUE, bool $unicode = TRUE): DefaultTheme {
    return ThemeManager::create('default', DefaultTheme::DEFAULT_WIDTH, ['color' => $color, 'unicode' => $unicode, 'mode' => Mode::Dark]);
  }

}
