<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Tests\Fixtures\Theme\AccentOptionTheme;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\FieldStyle;
use DrevOps\Tui\Theme\HAlign;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\VAlign;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the theme spacing, border and custom display options.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ThemeOptionsTest extends TestCase {

  #[DataProvider('dataProviderBorderStyleAccessor')]
  public function testBorderStyleAccessor(array $options, Border $expected): void {
    $this->assertSame($expected, (new DefaultTheme(24, $options))->borderStyle());
  }

  public static function dataProviderBorderStyleAccessor(): \Iterator {
    // Unset draws the rounded box; only an explicit opt-out goes borderless.
    yield 'defaults' => [[], Border::Rounded];
    yield 'enum case' => [['border' => Border::Double], Border::Double];
    yield 'string value' => [['border' => 'line'], Border::Line];
    yield 'none' => [['border' => Border::None], Border::None];
  }

  public function testBorderCostsTheContentItsChromeColumns(): void {
    // A bordered frame spends a border column and a gutter each side, so what
    // is left for content is four columns narrower than the frame.
    $this->assertSame(20, (new DefaultTheme(24, ['border' => Border::Line]))->contentWidth());
    $this->assertSame(24, (new DefaultTheme(24, ['border' => Border::None]))->contentWidth());
  }

  public function testSpacingAccessor(): void {
    $this->assertSame(Spacing::Padded, (new DefaultTheme(40))->spacing());
    $this->assertSame(Spacing::Compact, (new DefaultTheme(40, ['spacing' => Spacing::Compact]))->spacing());
    $this->assertSame(Spacing::Normal, (new DefaultTheme(40, ['spacing' => 'normal']))->spacing());
  }

  public function testFieldStyleInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "field"');

    new DefaultTheme(40, ['field' => 'fancy']);
  }

  public function testFieldFlatInputHasPlainCaretNoFill(): void {
    $line = (new DefaultTheme(40))->fieldInput('ab', 'cd', 'ef');

    // Flat keeps the value with the caret glyph and a dimmed ghost - no fill.
    $this->assertStringNotContainsString("\033[30;47m", $line);
    $this->assertStringNotContainsString("\033[97;44m", $line);
    $this->assertStringContainsString('ab', Ansi::strip($line));
    $this->assertStringContainsString('cd', Ansi::strip($line));
  }

  public function testFieldBoxedInputFillsBehindTheValue(): void {
    // A string value (not the enum case) exercises the option's string path.
    $line = (new DefaultTheme(40, ['field' => 'boxed', 'border' => Border::None]))->fieldInput('localhost', '');

    // The fill opens before the value, so the background runs behind the text
    // itself, and the field is padded to a fixed, visible width.
    $this->assertStringStartsWith("\033[30;47m", $line);
    $this->assertStringContainsString("\033[30;47mlocalhost", $line);
    $this->assertSame(40, Ansi::width($line));
    $this->assertStringEndsWith("\033[0m", $line);
  }

  public function testFieldBoxedEmptyInputIsVisible(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'border' => Border::None]))->fieldInput('', '');

    // An empty buffer still renders a full-width filled bar (caret + pad).
    $this->assertStringStartsWith("\033[30;47m", $line);
    $this->assertSame(40, Ansi::width($line));
  }

  public function testFieldBoxedInputAdaptsToLightMode(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'mode' => Mode::Light]))->fieldInput('x', '');

    // Light mode fills dark (white on blue) for contrast on a light terminal.
    $this->assertStringStartsWith("\033[97;44m", $line);
  }

  public function testFieldUnderlineInputUnderlinesField(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Underline]))->fieldInput('x', 'y');

    $this->assertStringStartsWith("\033[4;32m", $line);
    $this->assertStringContainsString('x', Ansi::strip($line));
    $this->assertStringContainsString('y', Ansi::strip($line));
  }

  public function testFieldBoxedInputCaretShowsTheLetter(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed]))->fieldInput('ab', 'cd');

    // The caret reverses the character it sits on ('c'), so the letter shows
    // through the cursor rather than a solid block.
    $this->assertStringContainsString("\033[7mc\033[27m", $line);
  }

  public function testFieldBoxedInputFillRunsUnbrokenThroughCaretAndGhost(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed]))->fieldInput('ab', 'cd', 'xyz');

    // The caret (reverse) and ghost (dim) toggle off rather than reset, so the
    // fill is never punctured: exactly one closing reset in the whole line.
    $this->assertSame(1, substr_count($line, "\033[0m"));
    $this->assertStringContainsString("\033[2mxyz\033[22m", $line);
  }

  public function testFieldInputNoColourFallsBackToFlat(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'color' => FALSE]))->fieldInput('ab', 'cd');

    // No colour: no SGR and no padding, just the value with the ascii caret.
    $this->assertStringNotContainsString("\033[", $line);
    $this->assertStringContainsString('ab', $line);
    $this->assertStringContainsString('cd', $line);
  }

  public function testUnknownOptionThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown theme option "spacng"');

    new DefaultTheme(40, ['spacng' => 'padded']);
  }

  public function testInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "spacing"');

    new DefaultTheme(40, ['spacing' => 'padd']);
  }

  #[DataProvider('dataProviderLayoutOptionAccessors')]
  public function testLayoutOptionAccessors(array $options, bool $fullscreen, HAlign $halign, VAlign $valign): void {
    $theme = new DefaultTheme(40, $options);

    $this->assertSame($fullscreen, $theme->isFullscreen());
    $this->assertSame($halign, $theme->halign());
    $this->assertSame($valign, $theme->valign());
  }

  public static function dataProviderLayoutOptionAccessors(): \Iterator {
    yield 'defaults' => [[], FALSE, HAlign::Left, VAlign::Top];
    yield 'enum cases' => [['fullscreen' => TRUE, 'halign' => HAlign::Center, 'valign' => VAlign::Middle], TRUE, HAlign::Center, VAlign::Middle];
    yield 'string values' => [['fullscreen' => TRUE, 'halign' => 'right', 'valign' => 'bottom'], TRUE, HAlign::Right, VAlign::Bottom];
  }

  #[DataProvider('dataProviderLayoutOptionInvalidValueThrows')]
  public function testLayoutOptionInvalidValueThrows(string $option, mixed $value, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    new DefaultTheme(40, [$option => $value]);
  }

  public static function dataProviderLayoutOptionInvalidValueThrows(): \Iterator {
    yield 'halign typo' => ['halign', 'centre', 'is not a valid "halign"'];
    yield 'valign typo' => ['valign', 'center', 'is not a valid "valign"'];
    yield 'fullscreen non-bool' => ['fullscreen', 'yes', 'is not a valid "fullscreen"'];
    yield 'negative min width' => ['min_width', -1, 'is not a valid "min_width"'];
    yield 'non-integer max width' => ['max_width', '100', 'is not a valid "max_width"'];
  }

  public function testUnknownOptionMessageListsIntegerOptions(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('min_width');

    new DefaultTheme(40, ['min_wdith' => 10]);
  }

  #[DataProvider('dataProviderContradictoryMinMaxThrows')]
  public function testContradictoryMinMaxThrows(array $options, string $message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);

    new DefaultTheme(40, $options);
  }

  public static function dataProviderContradictoryMinMaxThrows(): \Iterator {
    yield 'width' => [['min_width' => 100, 'max_width' => 50], '"min_width" must not exceed "max_width"'];
    yield 'height' => [['min_height' => 20, 'max_height' => 8], '"min_height" must not exceed "max_height"'];
  }

  #[DataProvider('dataProviderCompatibleMinMaxPasses')]
  public function testCompatibleMinMaxPasses(array $options): void {
    // Equal bounds, an uncapped maximum and an implicit default minimum are
    // all satisfiable, so none of them throw at declaration.
    $this->assertInstanceOf(DefaultTheme::class, new DefaultTheme(40, $options));
  }

  public static function dataProviderCompatibleMinMaxPasses(): \Iterator {
    yield 'equal bounds' => [['min_width' => 50, 'max_width' => 50]];
    yield 'uncapped maximum' => [['min_width' => 100, 'max_width' => 0]];
    yield 'default minimum under a low cap' => [['max_height' => 8]];
  }

  public function testSizeOptionAccessorsAndDefaults(): void {
    $defaults = new DefaultTheme(40);
    $this->assertSame(0, $defaults->minWidth());
    $this->assertSame(10, $defaults->minHeight());
    $this->assertSame(0, $defaults->maxWidth());
    $this->assertSame(0, $defaults->maxHeight());

    $theme = new DefaultTheme(40, ['min_width' => 50, 'min_height' => 12, 'max_width' => 100, 'max_height' => 40]);
    $this->assertSame(50, $theme->minWidth());
    $this->assertSame(12, $theme->minHeight());
    $this->assertSame(100, $theme->maxWidth());
    $this->assertSame(40, $theme->maxHeight());
  }

  public function testFullscreenMaxWidthCapsTheFrame(): void {
    // The cap narrows a fullscreen frame; uncapped keeps the terminal width.
    $this->assertSame(100, (new DefaultTheme(200, ['fullscreen' => TRUE, 'max_width' => 100, 'border' => Border::None]))->contentWidth());
    $this->assertSame(200, (new DefaultTheme(200, ['fullscreen' => TRUE, 'border' => Border::None]))->contentWidth());

    // A cap wider than the terminal never widens the frame.
    $this->assertSame(80, (new DefaultTheme(80, ['fullscreen' => TRUE, 'max_width' => 100, 'border' => Border::None]))->contentWidth());

    // Outside fullscreen the cap has no effect on sizing.
    $this->assertSame(200, (new DefaultTheme(200, ['max_width' => 100, 'border' => Border::None]))->contentWidth());
  }

  public function testCustomOptionDeclaredBySchema(): void {
    $theme = $this->accentTheme(['color' => FALSE, 'accent' => 'warm']);
    $this->assertSame('warm', $theme->accentOption());

    // Unset falls back to the theme's default.
    $this->assertSame('cool', $this->accentTheme(['color' => FALSE])->accentOption());
  }

  public function testCustomOptionInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "accent"');

    $this->accentTheme(['accent' => 'hot']);
  }

  public function testNonStringOptionFallsBackToDefault(): void {
    // "color" is a bool, so reading it as a string yields the default.
    $theme = new class(40, ['color' => FALSE]) extends DefaultTheme {

      public function colorAsString(): string {
        return $this->option('color', 'fallback');
      }

    };

    $this->assertSame('fallback', $theme->colorAsString());
  }

  /**
   * A theme declaring a custom "accent" option.
   *
   * @param array<string,mixed> $options
   *   The theme options.
   *
   * @return \DrevOps\Tui\Tests\Fixtures\Theme\AccentOptionTheme
   *   The theme.
   */
  protected function accentTheme(array $options): AccentOptionTheme {
    return new AccentOptionTheme($options);
  }

}
