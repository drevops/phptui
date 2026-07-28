<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\DosTheme;
use DrevOps\Tui\Theme\EmberTheme;
use DrevOps\Tui\Theme\FrostTheme;
use DrevOps\Tui\Theme\MidnightTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\MonoTheme;
use DrevOps\Tui\Theme\Sgr;
use DrevOps\Tui\Theme\ThemeManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the curated built-in themes' palettes, per mode.
 */
#[CoversClass(MidnightTheme::class)]
#[CoversClass(FrostTheme::class)]
#[CoversClass(EmberTheme::class)]
#[CoversClass(MonoTheme::class)]
#[CoversClass(DosTheme::class)]
#[CoversClass(Sgr::class)]
#[Group('tui')]
final class BuiltinThemesTest extends TestCase {

  /**
   * Each theme recolours its five palette roles, per mode.
   */
  #[DataProvider('dataProviderPalette')]
  public function testPalette(string $name, Mode $mode, array $expected): void {
    $theme = ThemeManager::create($name, 76, ['mode' => $mode]);

    // title, indicator, highlightMatch and border wrap text in the role SGR;
    // an unselected value carries no added weight, so it is the value SGR too.
    $this->assertSame(Ansi::style('X', $expected['accent']), $theme->title('X'));
    $this->assertSame(Ansi::style('X', $expected['accent']), $theme->highlight('X'));
    $this->assertSame(Ansi::style('X', $expected['value']), $theme->value('X'));
    $this->assertSame(Ansi::style('X', $expected['indicator']), $theme->indicator('X'));
    $this->assertSame(Ansi::style('X', $expected['match']), $theme->highlightMatch('X'));
    $this->assertSame(Ansi::style('X', $expected['border']), $theme->border('X'));
  }

  public static function dataProviderPalette(): \Iterator {
    yield 'midnight dark' => ['midnight', Mode::Dark, ['accent' => '1;38;5;141', 'value' => '38;5;114', 'indicator' => '38;5;212', 'match' => '38;5;212', 'border' => '38;5;97']];
    yield 'midnight light' => ['midnight', Mode::Light, ['accent' => '1;38;5;54', 'value' => '38;5;28', 'indicator' => '38;5;162', 'match' => '38;5;162', 'border' => '38;5;61']];
    yield 'frost dark' => ['frost', Mode::Dark, ['accent' => '1;38;5;117', 'value' => '38;5;150', 'indicator' => '38;5;222', 'match' => '38;5;222', 'border' => '38;5;109']];
    yield 'frost light' => ['frost', Mode::Light, ['accent' => '1;38;5;25', 'value' => '38;5;65', 'indicator' => '38;5;136', 'match' => '38;5;136', 'border' => '38;5;66']];
    yield 'ember dark' => ['ember', Mode::Dark, ['accent' => '1;38;5;208', 'value' => '38;5;142', 'indicator' => '38;5;214', 'match' => '38;5;214', 'border' => '38;5;130']];
    yield 'ember light' => ['ember', Mode::Light, ['accent' => '1;38;5;166', 'value' => '38;5;100', 'indicator' => '38;5;172', 'match' => '38;5;172', 'border' => '38;5;94']];
    yield 'mono dark' => ['mono', Mode::Dark, ['accent' => '1;97', 'value' => '38;5;250', 'indicator' => '1', 'match' => '7', 'border' => '38;5;244']];
    yield 'mono light' => ['mono', Mode::Light, ['accent' => '1;30', 'value' => '38;5;240', 'indicator' => '1', 'match' => '7', 'border' => '38;5;246']];
    yield 'dos dark' => ['dos', Mode::Dark, ['accent' => '1;97', 'value' => '96', 'indicator' => '93', 'match' => '93', 'border' => '97']];
    yield 'dos light' => ['dos', Mode::Light, ['accent' => '1;97', 'value' => '96', 'indicator' => '93', 'match' => '93', 'border' => '97']];
  }

  /**
   * A selected value keeps the palette hue and gains bold weight.
   */
  #[DataProvider('dataProviderSelectedValueIsBold')]
  public function testSelectedValueIsBold(string $name, string $expected): void {
    $theme = ThemeManager::create($name, 76, ['mode' => Mode::Dark]);

    $this->assertSame(Ansi::style('X', $expected), $theme->value('X', TRUE));
  }

  public static function dataProviderSelectedValueIsBold(): \Iterator {
    yield 'midnight' => ['midnight', '1;38;5;114'];
    yield 'frost' => ['frost', '1;38;5;150'];
    yield 'ember' => ['ember', '1;38;5;142'];
    yield 'mono' => ['mono', '1;38;5;250'];
    yield 'dos' => ['dos', '1;96'];
  }

  /**
   * With colour off every palette role degrades to plain text.
   */
  #[DataProvider('dataProviderColourOffStripsPalette')]
  public function testColourOffStripsPalette(string $name): void {
    $theme = ThemeManager::create($name, 76, ['color' => FALSE]);

    $this->assertFalse($theme->hasColor());
    $this->assertSame('Setup', $theme->title('Setup'));
    $this->assertSame('X', $theme->value('X', TRUE));
    $this->assertSame('X', $theme->indicator('X'));
    $this->assertSame('X', $theme->highlightMatch('X'));
    $this->assertSame('X', $theme->border('X'));
  }

  public static function dataProviderColourOffStripsPalette(): \Iterator {
    yield 'midnight' => ['midnight'];
    yield 'frost' => ['frost'];
    yield 'ember' => ['ember'];
    yield 'mono' => ['mono'];
    yield 'dos' => ['dos'];
  }

  /**
   * The dos theme frames its content in a double-line window by default.
   */
  public function testDosDefaultsToBorderedWindow(): void {
    $viewport = new Viewport(0, FALSE, FALSE);

    // With no border declared, dos draws its double-line MS-DOS window.
    $bordered = ThemeManager::create('dos', 40, ['color' => FALSE])->renderFrame(['Head'], ['Body'], [], $viewport, 1);
    $this->assertStringContainsString('═', $bordered);

    // An explicit border option still wins over the theme's default.
    $plain = ThemeManager::create('dos', 40, ['color' => FALSE, 'border' => 'none'])->renderFrame(['Head'], ['Body'], [], $viewport, 1);
    $this->assertStringNotContainsString('═', $plain);
  }

  /**
   * The dos theme washes the screen blue in either mode, colour permitting.
   */
  public function testDosPaintsBlueBackground(): void {
    $this->assertSame('44', ThemeManager::create('dos', 76, ['mode' => Mode::Dark])->background());
    $this->assertSame('44', ThemeManager::create('dos', 76, ['mode' => Mode::Light])->background());
    $this->assertNull(ThemeManager::create('dos', 76, ['color' => FALSE])->background());
  }

  /**
   * The dos theme paints secondary text in CGA light grey, not the dim grey.
   */
  #[DataProvider('dataProviderDosSecondaryTextIsLegibleOnBlue')]
  public function testDosSecondaryTextIsLegibleOnBlue(Mode $mode): void {
    $theme = ThemeManager::create('dos', 76, ['mode' => $mode]);

    $this->assertSame(Ansi::style('X', '37'), $theme->breadcrumb('X'));
    $this->assertSame(Ansi::style('X', '37'), $theme->footer('X'));
    $this->assertSame(Ansi::style('X', '37'), $theme->description('X'));
    $this->assertSame(Ansi::style('X', '1;37'), $theme->description('X', TRUE));
    $this->assertSame(Ansi::style('X', '1;37'), $theme->heading('X'));
  }

  public static function dataProviderDosSecondaryTextIsLegibleOnBlue(): \Iterator {
    yield 'dark' => [Mode::Dark];
    yield 'light' => [Mode::Light];
  }

  /**
   * CGA has no italic, so the dos hint takes a colour of its own instead.
   */
  #[DataProvider('dataProviderDosHintTakesItsOwnColourRatherThanItalic')]
  public function testDosHintTakesItsOwnColourRatherThanItalic(Mode $mode): void {
    $theme = ThemeManager::create('dos', 76, ['mode' => $mode]);

    $this->assertSame(Ansi::style('X', '96'), $theme->hint('X'));
    $this->assertSame(Ansi::style('X', '1;96'), $theme->hint('X', TRUE));
    $this->assertNotSame($theme->description('X'), $theme->hint('X'));
  }

  public static function dataProviderDosHintTakesItsOwnColourRatherThanItalic(): \Iterator {
    yield from self::dataProviderDosSecondaryTextIsLegibleOnBlue();
  }

}
