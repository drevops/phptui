<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Theme;

use DrevOps\PhpTui\Theme\DefaultTheme;
use DrevOps\PhpTui\Theme\Override\BreadcrumbOverrides;
use DrevOps\PhpTui\Theme\Override\FieldOverrides;
use DrevOps\PhpTui\Theme\Override\Glyph;
use DrevOps\PhpTui\Theme\Override\LegendOverrides;
use DrevOps\PhpTui\Theme\Override\Overrides;
use DrevOps\PhpTui\Theme\Override\ThemeElement;
use DrevOps\PhpTui\Theme\Sgr;
use DrevOps\PhpTui\Theme\ThemeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests patching a theme's elements without subclassing it.
 */
#[CoversClass(ThemeBuilder::class)]
#[CoversClass(Overrides::class)]
#[CoversClass(Glyph::class)]
#[CoversClass(BreadcrumbOverrides::class)]
#[CoversClass(LegendOverrides::class)]
#[CoversClass(FieldOverrides::class)]
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ThemeBuilderTest extends TestCase {

  #[DataProvider('dataProviderOverrideChangesItsElementAndNothingElse')]
  public function testOverrideChangesItsElementAndNothingElse(Overrides $overrides, string $element, bool $unicode): void {
    $options = ['unicode' => $unicode];
    $before = $this->elements(new DefaultTheme(80, $options));
    $after = $this->elements((new DefaultTheme(80, $options))->overrides($overrides));

    $this->assertNotSame($before[$element], $after[$element]);

    unset($before[$element], $after[$element]);
    $this->assertSame($before, $after);
  }

  public static function dataProviderOverrideChangesItsElementAndNothingElse(): \Iterator {
    $patches = [
      'breadcrumb separator' => [
        (new ThemeBuilder())->breadcrumb(static fn(BreadcrumbOverrides $b): BreadcrumbOverrides => $b->separator('»', '>>'))->overrides(),
        'breadcrumbSeparator',
      ],
      'legend separator' => [
        (new ThemeBuilder())->legend(static fn(LegendOverrides $l): LegendOverrides => $l->separator('•', '+'))->overrides(),
        'legendSeparator',
      ],
      'legend key' => [
        (new ThemeBuilder())->legend(static fn(LegendOverrides $l): LegendOverrides => $l->key(Sgr::Bold))->overrides(),
        'legendKey',
      ],
      'field selector' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->selector('→', '=>'))->overrides(),
        'fieldSelector',
      ],
      'field help marker' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->helpMarker('?', '(?)'))->overrides(),
        'fieldHelpMarker',
      ],
      'field value separator' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->valueSeparator(' | '))->overrides(),
        'fieldValueSeparator',
      ],
      'field option selector' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->optionSelector('→', '=>'))->overrides(),
        'fieldOptionSelector',
      ],
      'field option marker' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->optionMarker('★', '(*)'))->overrides(),
        'fieldOptionMarker',
      ],
      'field caret' => [
        (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->caret('▌', '!'))->overrides(),
        'fieldCaret',
      ],
    ];

    foreach ($patches as $name => [$overrides, $element]) {
      yield $name . ', unicode' => [$overrides, $element, TRUE];
      yield $name . ', ascii' => [$overrides, $element, FALSE];
    }
  }

  public function testGlyphOverrideIsStatedForBothDisplayModesAtOnce(): void {
    $overrides = (new ThemeBuilder())
      ->breadcrumb(static fn(BreadcrumbOverrides $b): BreadcrumbOverrides => $b->separator('»', '->'))
      ->overrides();

    $unicode = (new DefaultTheme(80, ['color' => FALSE, 'unicode' => TRUE]))->overrides($overrides);
    $ascii = (new DefaultTheme(80, ['color' => FALSE, 'unicode' => FALSE]))->overrides($overrides);

    $this->assertSame('»', $unicode->breadcrumbSeparator());
    $this->assertSame('->', $ascii->breadcrumbSeparator());
  }

  public function testOverrideNamesTheMarkAndTheThemeGoesOnPaintingIt(): void {
    $theme = (new DefaultTheme(80))->overrides(
      (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->selector('→', '=>'))->overrides()
    );

    $this->assertSame($theme->fieldOption('→', FALSE, TRUE), $theme->fieldSelector(TRUE));
  }

  public function testTwoSelectorsComeApartOnceEitherIsOverridden(): void {
    // They share one atom until a consumer says otherwise, which is the whole
    // reason each is an element of its own.
    $theme = (new DefaultTheme(80, ['color' => FALSE]))->overrides(
      (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->selector('→', '=>'))->overrides()
    );

    $this->assertSame('→', $theme->fieldSelector(TRUE));
    $this->assertSame('❯', $theme->fieldOptionSelector(TRUE));
  }

  public function testAnUnmarkedStateKeepsWhatTheThemeDrawsForIt(): void {
    $plain = new DefaultTheme(80, ['color' => FALSE]);
    $theme = (new DefaultTheme(80, ['color' => FALSE]))->overrides(
      (new ThemeBuilder())->field(static fn(FieldOverrides $f): FieldOverrides => $f->optionMarker('★', '(*)'))->overrides()
    );

    $this->assertSame('★', $theme->fieldOptionMarker(TRUE));
    $this->assertSame($plain->fieldOptionMarker(FALSE), $theme->fieldOptionMarker(FALSE));
    $this->assertSame(' ', $theme->fieldSelector(FALSE));
  }

  public function testOneGroupCanStateSeveralElementsAtOnce(): void {
    $overrides = (new ThemeBuilder())
      ->field(static fn(FieldOverrides $f): FieldOverrides => $f
        ->selector('→', '=>')
        ->helpMarker('?', '(?)')
        ->valueSeparator(' | ')
        ->optionMarker('★', '(*)')
        ->caret('▌', '!'))
      ->overrides();

    $theme = (new DefaultTheme(80, ['color' => FALSE]))->overrides($overrides);

    $this->assertSame('→', $theme->fieldSelector(TRUE));
    $this->assertSame('?', $theme->fieldHelpMarker());
    $this->assertSame(' | ', $theme->fieldValueSeparator());
    $this->assertSame('★', $theme->fieldOptionMarker(TRUE));
    $this->assertSame('▌', $theme->fieldCaret());
  }

  public function testPatchHoldsOnlyWhatWasStated(): void {
    $overrides = (new ThemeBuilder())
      ->legend(static fn(LegendOverrides $l): LegendOverrides => $l->key(Sgr::Bold, Sgr::Cyan))
      ->overrides();

    $this->assertSame(Sgr::of(Sgr::Bold, Sgr::Cyan), $overrides->style(ThemeElement::LegendKey));
    $this->assertNull($overrides->style(ThemeElement::FieldSelector));
    $this->assertNotInstanceOf(Glyph::class, $overrides->glyph(ThemeElement::BreadcrumbSeparator));
    $this->assertNull($overrides->text(ThemeElement::FieldValueSeparator));
  }

  public function testThemeTakesNoOverridesUntilItIsGivenSome(): void {
    $theme = new DefaultTheme(80, ['color' => FALSE]);

    $this->assertSame('›', $theme->breadcrumbSeparator());
    $this->assertSame('❯', $theme->fieldSelector(TRUE));
  }

  /**
   * Every element an override can reach, drawn once.
   *
   * @param \DrevOps\PhpTui\Theme\DefaultTheme $theme
   *   The theme.
   *
   * @return array<string,string>
   *   The drawn elements, keyed by the method that drew them.
   */
  protected function elements(DefaultTheme $theme): array {
    return [
      'breadcrumbLabel' => $theme->breadcrumbLabel('Orchard'),
      'breadcrumbSeparator' => $theme->breadcrumbSeparator(),
      'legendKey' => $theme->legendKey('esc'),
      'legendDescription' => $theme->legendDescription('to cancel'),
      'legendSeparator' => $theme->legendSeparator(),
      'chromeBorder' => $theme->chromeBorder('----'),
      'chromeOverflowMarker' => $theme->chromeOverflowMarker(TRUE),
      'fieldSelector' => $theme->fieldSelector(TRUE),
      'fieldLabel' => $theme->fieldLabel('Basket'),
      'fieldHelpMarker' => $theme->fieldHelpMarker(),
      'fieldValue' => $theme->fieldValue('apple'),
      'fieldValueSeparator' => $theme->fieldValueSeparator(),
      'fieldBadge' => $theme->fieldBadge('edited'),
      'fieldDescription' => $theme->fieldDescription('Pick the produce.'),
      'fieldOption' => $theme->fieldOption('Apple', TRUE),
      'fieldOptionSelector' => $theme->fieldOptionSelector(TRUE),
      'fieldOptionMarker' => $theme->fieldOptionMarker(TRUE),
      'fieldOptionNote' => $theme->fieldOptionNote('out of season'),
      'fieldOptionDescription' => $theme->fieldOptionDescription('Stays crisp.'),
      'fieldConstraint' => $theme->fieldConstraint('Pick two.'),
      'fieldError' => $theme->fieldError('Pick at least two.'),
      'fieldCaret' => $theme->fieldCaret(),
      'fieldDraft' => $theme->fieldDraft('Valley'),
      'fieldState' => $theme->fieldState('Filling fruit'),
      'fieldCaption' => $theme->fieldCaption('orchard/harvest'),
      'panelTitle' => $theme->panelTitle('Delivery'),
      'markupTitle' => $theme->markupTitle('Yields'),
      'markupLine' => $theme->markupLine('Twelve crates.'),
      'actionButton' => $theme->actionButton('Submit'),
      'actionSelected' => $theme->actionSelected('Submit'),
      'actionSeparator' => $theme->actionSeparator(),
      'progressCaption' => $theme->progressCaption('Packing crates'),
      'progressSpinner' => $theme->progressSpinner(0),
      'progressTrack' => $theme->progressTrack(4, 10),
      'progressCount' => $theme->progressCount(4, 10),
    ];
  }

}
