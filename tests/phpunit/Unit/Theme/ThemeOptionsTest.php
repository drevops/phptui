<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Viewport;
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

  #[DataProvider('dataProviderBorderDrawsBox')]
  public function testBorderDrawsBox(bool $unicode, Border $border, string $expected): void {
    $theme = new DefaultTheme(24, ['color' => FALSE, 'unicode' => $unicode, 'border' => $border]);
    $frame = $theme->renderFrame(['HEAD'], ['body'], ['FOOT'], new Viewport(0, FALSE, FALSE), 1);

    $this->assertStringContainsString($expected, $frame);
    $this->assertStringContainsString('HEAD', Ansi::strip($frame));
    $this->assertStringContainsString('body', Ansi::strip($frame));
    $this->assertStringContainsString('FOOT', Ansi::strip($frame));
  }

  public static function dataProviderBorderDrawsBox(): \Iterator {
    yield 'line' => [TRUE, Border::Line, '┌'];
    yield 'rounded' => [TRUE, Border::Rounded, '╭'];
    yield 'double' => [TRUE, Border::Double, '╔'];
    yield 'ascii line corner' => [FALSE, Border::Line, '+'];
    yield 'ascii double fill' => [FALSE, Border::Double, '='];
  }

  public function testBorderedLinesAreExactlyOuterWidthAndClip(): void {
    $theme = new DefaultTheme(12, ['color' => FALSE, 'border' => Border::Line]);

    // Inner width is 12 - 4 = 8, so a 20-char body line must clip; every line
    // is exactly the outer width.
    $frame = $theme->renderFrame(['H'], [str_repeat('x', 20)], ['F'], new Viewport(0, FALSE, FALSE), 1);

    foreach (explode("\n", $frame) as $line) {
      $this->assertSame(12, Ansi::width($line));
    }
  }

  public function testPaddedBorderAddsInnerPadding(): void {
    $padded = new DefaultTheme(20, ['color' => FALSE, 'spacing' => Spacing::Padded, 'border' => Border::Line]);
    $plain = new DefaultTheme(20, ['color' => FALSE, 'spacing' => Spacing::Normal, 'border' => Border::Line]);

    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // Padded adds a blank boxed line above and below the body.
    $this->assertSame(substr_count($plain->renderFrame(...$args), "\n") + 2, substr_count($padded->renderFrame(...$args), "\n"));
  }

  public function testBorderlessStatusGapFollowsSpacing(): void {
    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // Normal detaches the footer with a blank line; compact keeps it attached.
    $normal = explode("\n", Ansi::strip((new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Normal]))->renderFrame(...$args)));
    $this->assertSame(['H', 'b', '', 'F'], $normal);

    $compact = explode("\n", Ansi::strip((new DefaultTheme(40, ['color' => FALSE, 'border' => Border::None, 'spacing' => Spacing::Compact]))->renderFrame(...$args)));
    $this->assertSame(['H', 'b', 'F'], $compact);
  }

  public function testDefaultLookIsBorderedAndPadded(): void {
    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // Unset options draw the padded rounded box; only an explicit opt-out
    // goes borderless.
    $default = (new DefaultTheme(24, ['color' => FALSE]))->renderFrame(...$args);
    $explicit = (new DefaultTheme(24, ['color' => FALSE, 'border' => Border::Rounded, 'spacing' => Spacing::Padded]))->renderFrame(...$args);

    $this->assertSame($explicit, $default);
    $this->assertStringContainsString('╭', $default);
    $this->assertStringNotContainsString('╭', (new DefaultTheme(24, ['color' => FALSE, 'border' => Border::None]))->renderFrame(...$args));
  }

  public function testEditorAdoptsBorder(): void {
    $plain = (new DefaultTheme(30, ['color' => FALSE, 'border' => Border::None]))->renderEditor('Name', 'Acme');
    $boxed = (new DefaultTheme(30, ['color' => FALSE, 'border' => Border::Line]))->renderEditor('Name', 'Acme');

    // Borderless keeps today's label-over-rule editor; a border boxes it.
    $this->assertStringContainsString("Name\n────", Ansi::strip($plain));
    $this->assertStringNotContainsString('┌', $plain);

    $this->assertStringContainsString('┌', $boxed);
    $this->assertStringContainsString('Name', Ansi::strip($boxed));
    $this->assertStringContainsString('Acme', Ansi::strip($boxed));
  }

  public function testEditorDrawsHintsOnlyWhenGiven(): void {
    $plain = new DefaultTheme(30, ['color' => FALSE]);
    $keys = KeyMapManager::create()->forField(FieldType::Text);

    // An empty hint list draws no footer (the footer can be turned off); a
    // non-empty list draws it.
    $this->assertStringNotContainsString('accept', $plain->renderEditor('Name', 'body', [], $keys));
    $this->assertStringContainsString('accept', $plain->renderEditor('Name', 'body', [new Hint('accept', Action::Accept)], $keys));

    // Bordered: an empty hint list still closes the box with a single rule.
    $boxed = new DefaultTheme(30, ['color' => FALSE, 'border' => Border::Line]);
    $frame = $boxed->renderEditor('Name', 'body', [], $keys);
    $this->assertStringNotContainsString('accept', $frame);
    $this->assertStringContainsString('body', Ansi::strip($frame));
  }

  public function testBorderColourFollowsMode(): void {
    $args = [['H'], ['b'], ['F'], new Viewport(0, FALSE, FALSE), 1];

    // The border is drawn in the mode's border colour - cyan in dark, blue in
    // light - not the editor-rule grey.
    $dark = (new DefaultTheme(20, ['border' => Border::Line]))->renderFrame(...$args);
    $this->assertStringContainsString("\033[36m", $dark);

    $light = (new DefaultTheme(20, ['border' => Border::Line, 'mode' => Mode::Light]))->renderFrame(...$args);
    $this->assertStringContainsString("\033[34m", $light);
  }

  public function testFieldStyleInvalidValueThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid "field"');

    new DefaultTheme(40, ['field' => 'fancy']);
  }

  public function testFieldFlatInputHasPlainCaretNoFill(): void {
    $line = (new DefaultTheme(40))->renderInput('ab', 'cd', 'ef');

    // Flat keeps the value with the caret glyph and a dimmed ghost - no fill.
    $this->assertStringNotContainsString("\033[30;47m", $line);
    $this->assertStringNotContainsString("\033[97;44m", $line);
    $this->assertStringContainsString('ab', Ansi::strip($line));
    $this->assertStringContainsString('cd', Ansi::strip($line));
  }

  public function testFieldBoxedInputFillsBehindTheValue(): void {
    // A string value (not the enum case) exercises the option's string path.
    $line = (new DefaultTheme(40, ['field' => 'boxed', 'border' => Border::None]))->renderInput('localhost', '');

    // The fill opens before the value, so the background runs behind the text
    // itself, and the field is padded to a fixed, visible width.
    $this->assertStringStartsWith("\033[30;47m", $line);
    $this->assertStringContainsString("\033[30;47mlocalhost", $line);
    $this->assertSame(40, Ansi::width($line));
    $this->assertStringEndsWith("\033[0m", $line);
  }

  public function testFieldBoxedEmptyInputIsVisible(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'border' => Border::None]))->renderInput('', '');

    // An empty buffer still renders a full-width filled bar (caret + pad).
    $this->assertStringStartsWith("\033[30;47m", $line);
    $this->assertSame(40, Ansi::width($line));
  }

  public function testFieldBoxedInputAdaptsToLightMode(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'mode' => Mode::Light]))->renderInput('x', '');

    // Light mode fills dark (white on blue) for contrast on a light terminal.
    $this->assertStringStartsWith("\033[97;44m", $line);
  }

  public function testFieldUnderlineInputUnderlinesField(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Underline]))->renderInput('x', 'y');

    $this->assertStringStartsWith("\033[4;32m", $line);
    $this->assertStringContainsString('x', Ansi::strip($line));
    $this->assertStringContainsString('y', Ansi::strip($line));
  }

  public function testFieldBoxedInputCaretShowsTheLetter(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed]))->renderInput('ab', 'cd');

    // The caret reverses the character it sits on ('c'), so the letter shows
    // through the cursor rather than a solid block.
    $this->assertStringContainsString("\033[7mc\033[27m", $line);
  }

  public function testFieldBoxedInputFillRunsUnbrokenThroughCaretAndGhost(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed]))->renderInput('ab', 'cd', 'xyz');

    // The caret (reverse) and ghost (dim) toggle off rather than reset, so the
    // fill is never punctured: exactly one closing reset in the whole line.
    $this->assertSame(1, substr_count($line, "\033[0m"));
    $this->assertStringContainsString("\033[2mxyz\033[22m", $line);
  }

  public function testFieldInputNoColourFallsBackToFlat(): void {
    $line = (new DefaultTheme(40, ['field' => FieldStyle::Boxed, 'color' => FALSE]))->renderInput('ab', 'cd');

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
    $this->assertSame(100, (new DefaultTheme(200, ['fullscreen' => TRUE, 'max_width' => 100]))->outerWidth());
    $this->assertSame(200, (new DefaultTheme(200, ['fullscreen' => TRUE]))->outerWidth());

    // A cap wider than the terminal never widens the frame.
    $this->assertSame(80, (new DefaultTheme(80, ['fullscreen' => TRUE, 'max_width' => 100]))->outerWidth());

    // Outside fullscreen the cap has no effect on sizing.
    $this->assertSame(200, (new DefaultTheme(200, ['max_width' => 100]))->outerWidth());
  }

  public function testCustomOptionDeclaredBySchema(): void {
    $theme = $this->accentTheme(['color' => FALSE, 'accent' => 'warm']);
    $this->assertSame('warm', $theme->accent());

    // Unset falls back to the theme's default.
    $this->assertSame('cool', $this->accentTheme(['color' => FALSE])->accent());
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
