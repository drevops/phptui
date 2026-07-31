<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Tests\Fixtures\Theme\OceanTheme;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\MonoTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that elements sharing one hue move together, and only together.
 *
 * An element carries its own styling, and where two of them must agree on a
 * colour they draw it from the same place - which is what lets a theme repaint
 * a family in a line without knowing an element exists.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ElementDelegationTest extends TestCase {

  #[DataProvider('dataProviderElementsSharingOneHueAreDrawnAlike')]
  public function testElementsSharingOneHueAreDrawnAlike(\Closure $one, \Closure $other): void {
    // Colour on, so the assertion compares the painted bytes rather than the
    // plain text both would fall back to.
    $theme = new DefaultTheme(80);

    $this->assertSame($other($theme), $one($theme));
  }

  public static function dataProviderElementsSharingOneHueAreDrawnAlike(): \Iterator {
    yield 'the two titles' => [
      static fn(DefaultTheme $t): string => $t->panelTitle('Delivery'),
      static fn(DefaultTheme $t): string => $t->markupTitle('Delivery'),
    ];
    yield 'the three selectors' => [
      static fn(DefaultTheme $t): string => $t->fieldSelector(TRUE),
      static fn(DefaultTheme $t): string => $t->panelSelector(TRUE),
    ];
    yield 'the field and entry selectors' => [
      static fn(DefaultTheme $t): string => $t->fieldSelector(TRUE),
      static fn(DefaultTheme $t): string => $t->fieldEntrySelector(TRUE),
    ];
    yield 'a value and a summary of values' => [
      static fn(DefaultTheme $t): string => $t->fieldValue('apple'),
      static fn(DefaultTheme $t): string => $t->panelSummary('apple'),
    ];
    yield 'a field description and a line of markup' => [
      static fn(DefaultTheme $t): string => $t->fieldDescription('Pick the produce.'),
      static fn(DefaultTheme $t): string => $t->markupLine('Pick the produce.'),
    ];
    yield 'a panel description and a line of markup' => [
      static fn(DefaultTheme $t): string => $t->panelDescription('Pick the produce.'),
      static fn(DefaultTheme $t): string => $t->markupLine('Pick the produce.'),
    ];
    yield 'the focused entry and the caret' => [
      static fn(DefaultTheme $t): string => $t->fieldEntry('█', FALSE, TRUE),
      static fn(DefaultTheme $t): string => $t->fieldCaret(),
    ];
    yield 'the rule and the entry separator' => [
      static fn(DefaultTheme $t): string => $t->renderRule(),
      static fn(DefaultTheme $t): string => $t->fieldEntrySeparator(),
    ];
  }

  #[DataProvider('dataProviderRepaintingOneHueMovesEveryElementDrawnFromIt')]
  public function testRepaintingOneHueMovesEveryElementDrawnFromIt(\Closure $element): void {
    // A named theme states a palette and never names an element, so the palette
    // is the only thing carrying its colours through.
    $default = new DefaultTheme(80);
    $mono = new MonoTheme(80);

    $this->assertNotSame($element($default), $element($mono));
  }

  public static function dataProviderRepaintingOneHueMovesEveryElementDrawnFromIt(): \Iterator {
    yield 'field selector' => [static fn(DefaultTheme $t): string => $t->fieldSelector(TRUE)];
    yield 'field entry selector' => [static fn(DefaultTheme $t): string => $t->fieldEntrySelector(TRUE)];
    yield 'field value' => [static fn(DefaultTheme $t): string => $t->fieldValue('apple')];
    yield 'field caret' => [static fn(DefaultTheme $t): string => $t->fieldCaret()];
    yield 'field entry marker' => [static fn(DefaultTheme $t): string => $t->fieldEntryMarker(TRUE, TRUE)];
    yield 'chrome border' => [static fn(DefaultTheme $t): string => $t->chromeBorder('----')];
    yield 'chrome overflow marker' => [static fn(DefaultTheme $t): string => $t->chromeOverflowMarker(TRUE)];
    yield 'panel title' => [static fn(DefaultTheme $t): string => $t->panelTitle('Delivery')];
    yield 'progress spinner' => [static fn(DefaultTheme $t): string => $t->progressSpinner(0)];
  }

  public function testThemeSubclassReachesEveryElementThroughOneRepaintedHue(): void {
    // The fixture repaints the accent alone, and every element drawn from it
    // follows without the theme mentioning any of them.
    $ocean = new OceanTheme(80);
    $default = new DefaultTheme(80);

    $this->assertSame($ocean->panelTitle('Delivery'), $ocean->markupTitle('Delivery'));
    $this->assertNotSame($default->panelTitle('Delivery'), $ocean->panelTitle('Delivery'));
    $this->assertNotSame($default->fieldSelector(TRUE), $ocean->fieldSelector(TRUE));
    $this->assertNotSame($default->fieldCaret(), $ocean->fieldCaret());

    // What the accent does not reach is untouched.
    $this->assertSame($default->fieldValue('apple'), $ocean->fieldValue('apple'));
  }

}
