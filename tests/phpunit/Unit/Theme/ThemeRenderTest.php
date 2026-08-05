<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the finished pieces the theme composes from its elements.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ThemeRenderTest extends TestCase {

  use BuildsThemesTrait;

  public function testHelpMarkerFallsBackToTextWithoutUnicode(): void {
    // The glyph is narrow by design so a fixed-advance surface cannot squeeze
    // it, but a surface with no Unicode at all still needs a mark.
    $this->assertSame('ⁱ', Ansi::strip((new DefaultTheme(40, ['color' => FALSE]))->fieldHelpMarker()));
    $this->assertSame('[?]', Ansi::strip((new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE]))->fieldHelpMarker()));
  }

  public function testRenderTableDrawsAlignedGrid(): void {
    // The borderless theme falls back to the single-line box for an explicit
    // table, exactly as a bordered note does.
    $expected = [
      '┌───────┬────────┐',
      '│ Fruit │ Colour │',
      '├───────┼────────┤',
      '│ Apple │ Red    │',
      '└───────┴────────┘',
    ];

    $this->assertSame($expected, $this->plainTheme()->renderTable(['Fruit', 'Colour'], [['Apple', 'Red']]));
  }

  public function testRenderTableColorsCellsAndBorders(): void {
    $theme = new DefaultTheme(40, ['color' => TRUE, 'border' => Border::Line]);
    $joined = implode("\n", $theme->renderTable(['Fruit'], [['Apple']]));

    // Colour on wraps the cells and borders in ANSI; the grid still reads.
    $this->assertStringContainsString("\033[", $joined);
    $this->assertStringContainsString('Fruit', Ansi::strip($joined));
    $this->assertStringContainsString('Apple', Ansi::strip($joined));
  }

  public function testRenderTableHonoursUnicodeOff(): void {
    $theme = new DefaultTheme(40, ['color' => FALSE, 'unicode' => FALSE, 'border' => Border::Line]);
    $lines = $theme->renderTable(['Fruit'], [['Apple']]);

    $this->assertSame('+-------+', $lines[0]);
    $this->assertStringContainsString('| Apple |', implode("\n", $lines));
  }

  public function testRenderTableCapsAtFrameWidth(): void {
    // A narrow frame shrinks the column and ellipsizes the long cell so the
    // border stays whole - the inner width here is 10 - 4 = 6.
    $theme = new DefaultTheme(10, ['color' => FALSE, 'border' => Border::Line]);
    $lines = $theme->renderTable(['Name'], [['Watermelon']]);

    foreach ($lines as $line) {
      $this->assertLessThanOrEqual(6, Ansi::width($line));
    }
    $this->assertStringContainsString('…', implode("\n", $lines));
  }

  public function testBanner(): void {
    $banner = Ansi::strip($this->plainTheme()->renderBanner("LOGO\nline", '1.2.3'));

    $this->assertStringContainsString('LOGO', $banner);
    $this->assertStringContainsString('Version: 1.2.3', $banner);

    $this->assertStringNotContainsString('Version', Ansi::strip($this->plainTheme()->renderBanner('LOGO', '')));
  }

  #[DataProvider('dataProviderKeyGlyph')]
  public function testKeyGlyph(KeyName|string $key, string $unicode, string $ascii): void {
    $this->assertSame($unicode, (new DefaultTheme())->keyGlyph($key));
    $this->assertSame($ascii, (new DefaultTheme(76, ['color' => FALSE, 'unicode' => FALSE]))->keyGlyph($key));
  }

  public static function dataProviderKeyGlyph(): \Iterator {
    yield 'up' => [KeyName::Up, '↑', '^'];
    yield 'down' => [KeyName::Down, '↓', 'v'];
    yield 'left' => [KeyName::Left, '←', '<'];
    yield 'right' => [KeyName::Right, '→', '>'];
    yield 'enter' => [KeyName::Enter, '↵', '<'];
    yield 'escape' => [KeyName::Escape, 'esc', 'esc'];
    yield 'interrupt' => [KeyName::Interrupt, 'ctrl-c', 'ctrl-c'];
    yield 'tab' => [KeyName::Tab, 'tab', 'tab'];
    yield 'space' => [KeyName::Space, 'space', 'space'];
    yield 'backspace' => [KeyName::Backspace, '⌫', 'bksp'];
    yield 'delete' => [KeyName::Delete, 'del', 'del'];
    yield 'home' => [KeyName::Home, 'home', 'home'];
    yield 'end' => [KeyName::End, 'end', 'end'];
    yield 'page up' => [KeyName::PageUp, 'pgup', 'pgup'];
    yield 'page down' => [KeyName::PageDown, 'pgdn', 'pgdn'];
    yield 'wheel up' => [KeyName::MouseWheelUp, '↑', '^'];
    yield 'wheel down' => [KeyName::MouseWheelDown, '↓', 'v'];
    // A typed key arrives as what it writes, so there is no vocabulary left
    // for a theme to pick from and it is written out as it came.
    yield 'character' => ['j', 'j', 'j'];
    yield 'key spelled out where nothing can be typed' => ['ctrl-e', 'ctrl-e', 'ctrl-e'];
  }

  public function testDimRecedesText(): void {
    // With colour, dim wraps the text; with colour off, it is left untouched.
    $this->assertSame("\033[2mx\033[0m", (new DefaultTheme(40))->dim('x'));
    $this->assertSame('x', $this->plainTheme()->dim('x'));
  }

}
