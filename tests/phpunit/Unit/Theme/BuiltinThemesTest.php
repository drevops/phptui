<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Block\Prose;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Tests\Traits\BuildsThemesTrait;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DosTheme;
use DrevOps\Tui\Theme\EmberTheme;
use DrevOps\Tui\Theme\FrostTheme;
use DrevOps\Tui\Theme\MidnightTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\MonoTheme;
use DrevOps\Tui\Theme\Sgr;
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
#[Group('theme')]
final class BuiltinThemesTest extends TestCase {

  use BuildsThemesTrait;

  /**
   * Each theme recolours its five palette roles, per mode.
   */
  #[DataProvider('dataProviderPalette')]
  public function testPalette(string $name, Mode $mode, array $expected): void {
    $theme = $this->builtin($name, 76, ['mode' => $mode]);

    // A hue is stated once and every element drawn from it follows, so each
    // role is read back through an element rather than through the palette.
    $this->assertSame(Ansi::style('X', $expected['accent']), $theme->markupTitle('X'));
    $this->assertSame(Ansi::style('X', $expected['accent']), $theme->fieldOption('X', FALSE, TRUE));
    $this->assertSame(Ansi::style('X', $expected['value']), $theme->fieldValue('X'));
    $this->assertSame(Ansi::style('▲', $expected['indicator']), $theme->chromeOverflowMarker(TRUE));
    $this->assertSame(Ansi::style('X', $expected['match']), $theme->fieldOptionMatch('X'));
    $this->assertSame(Ansi::style('X', $expected['border']), $theme->chromeBorder('X'));
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
   * A picked option keeps the palette hue and gains weight.
   */
  #[DataProvider('dataProviderPickedOptionIsBold')]
  public function testPickedOptionIsBold(string $name, string $expected): void {
    $theme = $this->builtin($name, 76, ['mode' => Mode::Dark]);

    $this->assertSame(Ansi::style('X', $expected), $theme->panelSummary('X'));
  }

  public static function dataProviderPickedOptionIsBold(): \Iterator {
    yield 'midnight' => ['midnight', '38;5;114'];
    yield 'frost' => ['frost', '38;5;150'];
    yield 'ember' => ['ember', '38;5;142'];
    yield 'mono' => ['mono', '38;5;250'];
    yield 'dos' => ['dos', '96'];
  }

  /**
   * With colour off every palette role degrades to plain text.
   */
  #[DataProvider('dataProviderColourOffStripsPalette')]
  public function testColourOffStripsPalette(string $name): void {
    $theme = $this->builtin($name, 76, ['color' => FALSE]);

    $this->assertFalse($theme->hasColor());
    $this->assertSame('Setup', $theme->markupTitle('Setup'));
    $this->assertSame('X', $theme->fieldValue('X'));
    $this->assertSame('▲', $theme->chromeOverflowMarker(TRUE));
    $this->assertSame('X', $theme->fieldOptionMatch('X'));
    $this->assertSame('X', $theme->chromeBorder('X'));
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
    // With no border declared, dos draws its double-line MS-DOS window.
    $this->assertSame(Border::Double, $this->builtin('dos', 40, ['color' => FALSE])->borderStyle());

    // An explicit border option still wins over the theme's default.
    $this->assertSame(Border::None, $this->builtin('dos', 40, ['color' => FALSE, 'border' => 'none'])->borderStyle());
  }

  /**
   * The dos theme washes the screen blue in either mode, colour permitting.
   */
  public function testDosPaintsBlueBackground(): void {
    $this->assertSame('44', $this->builtin('dos', 76, ['mode' => Mode::Dark])->background());
    $this->assertSame('44', $this->builtin('dos', 76, ['mode' => Mode::Light])->background());
    $this->assertNull($this->builtin('dos', 76, ['color' => FALSE])->background());
  }

  /**
   * The dos theme paints secondary text in CGA light grey, not the dim grey.
   */
  #[DataProvider('dataProviderDosSecondaryTextIsLegibleOnBlue')]
  public function testDosSecondaryTextIsLegibleOnBlue(Mode $mode): void {
    $theme = $this->builtin('dos', 76, ['mode' => $mode]);

    $this->assertSame(Ansi::style('X', '37'), $theme->breadcrumbLabel('X'));
    $this->assertSame(Ansi::style('X', '37'), $theme->fieldState('X'));
    $this->assertSame(Ansi::style('X', '37'), $theme->fieldDescription('X'));
    $this->assertSame(Ansi::style('X', '37'), $theme->markupLine('X'));
  }

  public static function dataProviderDosSecondaryTextIsLegibleOnBlue(): \Iterator {
    yield 'dark' => [Mode::Dark];
    yield 'light' => [Mode::Light];
  }

  /**
   * A theme's heading hue reaches the pieces its elements are assembled into.
   */
  public function testDosHeadingReachesTheGridItHeads(): void {
    $theme = $this->builtin('dos', 40, ['border' => Border::Line]);

    $this->assertStringContainsString(Ansi::style('Fruit', '1;37'), implode("\n", $theme->renderTable(['Fruit'], [['Apple']])));
    $this->assertStringContainsString(Ansi::style('Yields', '1;37'), implode("\n", $theme->renderCard('Yields', [])));
  }

  /**
   * CGA has no italic, so the dos constraint takes a colour of its own.
   */
  #[DataProvider('dataProviderDosConstraintTakesItsOwnColour')]
  public function testDosConstraintTakesItsOwnColour(Mode $mode): void {
    $theme = $this->builtin('dos', 76, ['mode' => $mode]);

    $this->assertSame(Ansi::style('X', '96'), $theme->fieldConstraint('X'));
    $this->assertNotSame($theme->fieldDescription('X'), $theme->fieldConstraint('X'));
  }

  public static function dataProviderDosConstraintTakesItsOwnColour(): \Iterator {
    yield 'dark' => [Mode::Dark];
    yield 'light' => [Mode::Light];
  }

  /**
   * Every theme separates guidance from description by colour, not italic.
   *
   * A constraint is drawn directly beneath the highlighted option's own
   * description, so the two need a cue that survives the surface: an SVG
   * render carries colour but drops italic entirely.
   */
  #[DataProvider('dataProviderGuidanceTakesItsOwnColour')]
  public function testGuidanceTakesItsOwnColour(string $name, Mode $mode): void {
    $theme = $this->builtin($name, 76, ['mode' => $mode]);

    $constraint = $theme->fieldConstraint('X');
    $description = $theme->fieldDescription('X');

    $this->assertNotSame($description, $constraint);
    $this->assertNotSame($description, str_replace(Sgr::Italic->value . ';', '', $constraint));
  }

  public static function dataProviderGuidanceTakesItsOwnColour(): \Iterator {
    foreach (['default', 'midnight', 'frost', 'ember', 'mono', 'dos'] as $name) {
      yield $name . ' dark' => [$name, Mode::Dark];
      yield $name . ' light' => [$name, Mode::Light];
    }
  }

  /**
   * Guidance stays apart from a description with the colour switched off.
   *
   * Strip the surface back and neither hue nor italic survives, so the voice
   * has to fall back on something a plain terminal still carries.
   */
  #[DataProvider('dataProviderGuidanceSurvivesColourOff')]
  public function testGuidanceSurvivesColourOff(string $name, bool $unicode): void {
    $theme = $this->builtin($name, 76, ['color' => FALSE, 'unicode' => $unicode]);

    $this->assertNotSame($theme->fieldDescription('X'), $theme->fieldConstraint('X'));
  }

  public static function dataProviderGuidanceSurvivesColourOff(): \Iterator {
    foreach (['default', 'midnight', 'frost', 'ember', 'mono', 'dos'] as $name) {
      yield $name . ' unicode' => [$name, TRUE];
      yield $name . ' ascii' => [$name, FALSE];
    }
  }

  /**
   * A theme's line element reaches the body text, not only its own rows.
   */
  public function testDosDescriptionBodyCarriesTheThemeElement(): void {
    $dos = $this->builtin('dos', 76);
    $default = $this->builtin('default', 76);

    $line = Prose::lines('Picked this morning', $dos)[0];

    // The body is styled by the same element the one-line rows use, so the dos
    // theme's legible white reaches it instead of the dim grey it inherits.
    $this->assertSame($dos->markupLine('Picked this morning'), $line);
    $this->assertNotSame(Prose::lines('Picked this morning', $default)[0], $line);
  }

  /**
   * The bullet leading a list item is themed with the text beside it.
   */
  public function testDosBulletCarriesTheThemeElement(): void {
    $theme = $this->builtin('dos', 76, ['markdown' => TRUE]);

    $line = Prose::lines('- crisp apples', $theme)[0];

    $this->assertStringContainsString($theme->markupLine($theme->markupBullet() . ' '), $line);
    $this->assertStringContainsString($theme->markupLine('crisp apples'), $line);
  }

}
