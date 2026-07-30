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
 * Tests that an element draws from the atom it is styled by, and no other.
 *
 * One atom, one element: a palette is written once as an atom, and the element
 * that composes it follows - which is what keeps a subclass's colours effective
 * without it knowing an element exists.
 */
#[CoversClass(DefaultTheme::class)]
#[Group('theme')]
final class ElementDelegationTest extends TestCase {

  #[DataProvider('dataProviderElementDrawsWhatItsAtomDraws')]
  public function testElementDrawsWhatItsAtomDraws(\Closure $element, \Closure $atom): void {
    // Colour on, so the assertion compares the painted bytes rather than the
    // plain text both would fall back to.
    $theme = new DefaultTheme(80);

    $this->assertSame($atom($theme), $element($theme));
  }

  public static function dataProviderElementDrawsWhatItsAtomDraws(): \Iterator {
    yield 'chrome border' => [
      static fn(DefaultTheme $t): string => $t->chromeBorder('----'),
      static fn(DefaultTheme $t): string => $t->border('----'),
    ];
    yield 'chrome overflow marker above' => [
      static fn(DefaultTheme $t): string => $t->chromeOverflowMarker(TRUE),
      static fn(DefaultTheme $t): string => $t->indicator($t->indicatorUp()),
    ];
    yield 'chrome overflow marker below' => [
      static fn(DefaultTheme $t): string => $t->chromeOverflowMarker(FALSE),
      static fn(DefaultTheme $t): string => $t->indicator($t->indicatorDown()),
    ];
    yield 'breadcrumb label' => [
      static fn(DefaultTheme $t): string => $t->breadcrumbLabel('Orchard'),
      static fn(DefaultTheme $t): string => $t->breadcrumb('Orchard'),
    ];
    yield 'breadcrumb separator' => [
      static fn(DefaultTheme $t): string => $t->breadcrumbSeparator(),
      static fn(DefaultTheme $t): string => $t->separator(),
    ];
    yield 'field selector' => [
      static fn(DefaultTheme $t): string => $t->fieldSelector(TRUE),
      static fn(DefaultTheme $t): string => $t->marker(TRUE),
    ];
    yield 'field label' => [
      static fn(DefaultTheme $t): string => $t->fieldLabel('Basket'),
      static fn(DefaultTheme $t): string => $t->label('Basket'),
    ];
    yield 'field help marker' => [
      static fn(DefaultTheme $t): string => $t->fieldHelpMarker(),
      static fn(DefaultTheme $t): string => $t->helpMarker(),
    ];
    yield 'field value' => [
      static fn(DefaultTheme $t): string => $t->fieldValue('apple'),
      static fn(DefaultTheme $t): string => $t->value('apple'),
    ];
    yield 'field badge' => [
      static fn(DefaultTheme $t): string => $t->fieldBadge('edited'),
      static fn(DefaultTheme $t): string => $t->badge('edited'),
    ];
    yield 'field description' => [
      static fn(DefaultTheme $t): string => $t->fieldDescription('Pick the produce.'),
      static fn(DefaultTheme $t): string => $t->description('Pick the produce.'),
    ];
    yield 'field entry' => [
      static fn(DefaultTheme $t): string => $t->fieldEntry('Apple', TRUE),
      static fn(DefaultTheme $t): string => $t->label('Apple', TRUE),
    ];
    yield 'field entry marker' => [
      static fn(DefaultTheme $t): string => $t->fieldEntryMarker(TRUE),
      static fn(DefaultTheme $t): string => $t->check(TRUE),
    ];
    yield 'field entry note' => [
      static fn(DefaultTheme $t): string => $t->fieldEntryNote('out of season'),
      static fn(DefaultTheme $t): string => $t->disabled('out of season'),
    ];
    yield 'field entry description' => [
      static fn(DefaultTheme $t): string => $t->fieldEntryDescription('Stays crisp for weeks.'),
      static fn(DefaultTheme $t): string => $t->entryDescription('Stays crisp for weeks.'),
    ];
    yield 'field constraint' => [
      static fn(DefaultTheme $t): string => $t->fieldConstraint('Pick two.'),
      static fn(DefaultTheme $t): string => $t->hint('Pick two.'),
    ];
    yield 'field error' => [
      static fn(DefaultTheme $t): string => $t->fieldError('Pick at least two.'),
      static fn(DefaultTheme $t): string => $t->error('Pick at least two.'),
    ];
    yield 'field caret' => [
      static fn(DefaultTheme $t): string => $t->fieldCaret(),
      static fn(DefaultTheme $t): string => $t->caret(),
    ];
    yield 'field state' => [
      static fn(DefaultTheme $t): string => $t->fieldState('Filling fruit'),
      static fn(DefaultTheme $t): string => $t->footer('Filling fruit'),
    ];
    yield 'field caption' => [
      static fn(DefaultTheme $t): string => $t->fieldCaption('orchard/harvest'),
      static fn(DefaultTheme $t): string => $t->caption('orchard/harvest'),
    ];
    yield 'panel title' => [
      static fn(DefaultTheme $t): string => $t->panelTitle('Delivery'),
      static fn(DefaultTheme $t): string => $t->title('Delivery'),
    ];
    yield 'markup title' => [
      static fn(DefaultTheme $t): string => $t->markupTitle('Yields'),
      static fn(DefaultTheme $t): string => $t->title('Yields'),
    ];
    yield 'markup line' => [
      static fn(DefaultTheme $t): string => $t->markupLine('Twelve crates.'),
      static fn(DefaultTheme $t): string => $t->description('Twelve crates.'),
    ];
    yield 'action button' => [
      static fn(DefaultTheme $t): string => $t->actionButton('Submit'),
      static fn(DefaultTheme $t): string => $t->value('[ Submit ]'),
    ];
    yield 'action selected' => [
      static fn(DefaultTheme $t): string => $t->actionSelected('Submit'),
      static fn(DefaultTheme $t): string => $t->cursor('[ Submit ]'),
    ];
    yield 'progress spinner' => [
      static fn(DefaultTheme $t): string => $t->progressSpinner(0),
      static fn(DefaultTheme $t): string => $t->highlight('⠋'),
    ];
  }

  #[DataProvider('dataProviderRestylingAnAtomRestylesTheElementDrawnFromIt')]
  public function testRestylingAnAtomRestylesTheElementDrawnFromIt(\Closure $element): void {
    // A named theme states a palette by overriding atoms and never names an
    // element, so the delegation is the only thing carrying its colours
    // through.
    $default = new DefaultTheme(80);
    $mono = new MonoTheme(80);

    $this->assertNotSame($element($default), $element($mono));
  }

  public static function dataProviderRestylingAnAtomRestylesTheElementDrawnFromIt(): \Iterator {
    yield 'field selector' => [static fn(DefaultTheme $t): string => $t->fieldSelector(TRUE)];
    yield 'field entry selector' => [static fn(DefaultTheme $t): string => $t->fieldEntrySelector(TRUE)];
    yield 'field value' => [static fn(DefaultTheme $t): string => $t->fieldValue('apple')];
    yield 'field caret' => [static fn(DefaultTheme $t): string => $t->fieldCaret()];
    yield 'field entry marker' => [static fn(DefaultTheme $t): string => $t->fieldEntryMarker(TRUE)];
    yield 'chrome border' => [static fn(DefaultTheme $t): string => $t->chromeBorder('----')];
    yield 'panel title' => [static fn(DefaultTheme $t): string => $t->panelTitle('Delivery')];
  }

  public function testThemeSubclassReachesEveryElementThroughOneOverriddenAtom(): void {
    // The fixture restyles the title atom alone, and both elements drawn from
    // it follow without the theme mentioning either.
    $ocean = new OceanTheme(80);

    $this->assertSame($ocean->title('Delivery'), $ocean->panelTitle('Delivery'));
    $this->assertSame($ocean->title('Delivery'), $ocean->markupTitle('Delivery'));
    $this->assertNotSame((new DefaultTheme(80))->panelTitle('Delivery'), $ocean->panelTitle('Delivery'));
  }

}
