<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Tests\Fixtures\Theme\FloorTheme;
use DrevOps\Tui\Theme\AbstractTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the floor: every element answered with nothing but what it was given.
 */
#[CoversClass(AbstractTheme::class)]
#[Group('theme')]
final class AbstractThemeTest extends TestCase {

  #[DataProvider('dataProviderEveryStyledElementHandsBackTheStringItWasGiven')]
  public function testEveryStyledElementHandsBackTheStringItWasGiven(\Closure $draw): void {
    $this->assertSame('Orchard', $draw(new FloorTheme()));
  }

  public static function dataProviderEveryStyledElementHandsBackTheStringItWasGiven(): \Iterator {
    yield 'chrome border' => [static fn(FloorTheme $t): string => $t->chromeBorder('Orchard')];
    yield 'breadcrumb label' => [static fn(FloorTheme $t): string => $t->breadcrumbLabel('Orchard')];
    yield 'legend key' => [static fn(FloorTheme $t): string => $t->legendKey('Orchard')];
    yield 'legend description' => [static fn(FloorTheme $t): string => $t->legendDescription('Orchard')];
    yield 'field label' => [static fn(FloorTheme $t): string => $t->fieldLabel('Orchard')];
    yield 'field value' => [static fn(FloorTheme $t): string => $t->fieldValue('Orchard')];
    yield 'field badge' => [static fn(FloorTheme $t): string => $t->fieldBadge('Orchard')];
    yield 'field description' => [static fn(FloorTheme $t): string => $t->fieldDescription('Orchard')];
    yield 'field entry note' => [static fn(FloorTheme $t): string => $t->fieldEntryNote('Orchard')];
    yield 'field entry description' => [static fn(FloorTheme $t): string => $t->fieldEntryDescription('Orchard')];
    yield 'field constraint' => [static fn(FloorTheme $t): string => $t->fieldConstraint('Orchard')];
    yield 'field error' => [static fn(FloorTheme $t): string => $t->fieldError('Orchard')];
    yield 'field draft' => [static fn(FloorTheme $t): string => $t->fieldDraft('Orchard')];
    yield 'field state' => [static fn(FloorTheme $t): string => $t->fieldState('Orchard')];
    yield 'field caption' => [static fn(FloorTheme $t): string => $t->fieldCaption('Orchard')];
    yield 'panel title' => [static fn(FloorTheme $t): string => $t->panelTitle('Orchard')];
    yield 'markup title' => [static fn(FloorTheme $t): string => $t->markupTitle('Orchard')];
    yield 'markup line' => [static fn(FloorTheme $t): string => $t->markupLine('Orchard')];
    yield 'progress caption' => [static fn(FloorTheme $t): string => $t->progressCaption('Orchard')];
  }

  #[DataProvider('dataProviderEveryGlyphFallsBackToWhatAsciiCanDraw')]
  public function testEveryGlyphFallsBackToWhatAsciiCanDraw(string $drawn): void {
    // Nothing outside ASCII, because the floor declares no Unicode - and every
    // mark still reads, because a form has to be usable without one.
    $this->assertSame($drawn, preg_replace('/[^\x20-\x7E]/', '', $drawn));
  }

  public static function dataProviderEveryGlyphFallsBackToWhatAsciiCanDraw(): \Iterator {
    $floor = new FloorTheme();

    yield 'overflow marker above' => [$floor->chromeOverflowMarker(TRUE)];
    yield 'overflow marker below' => [$floor->chromeOverflowMarker(FALSE)];
    yield 'breadcrumb separator' => [$floor->breadcrumbSeparator()];
    yield 'legend separator' => [$floor->legendSeparator()];
    yield 'field selector' => [$floor->fieldSelector(TRUE)];
    yield 'field help marker' => [$floor->fieldHelpMarker()];
    yield 'field entry selector' => [$floor->fieldEntrySelector(TRUE)];
    yield 'field entry marker chosen' => [$floor->fieldEntryMarker(TRUE)];
    yield 'field entry marker unchosen' => [$floor->fieldEntryMarker(FALSE)];
    yield 'field caret' => [$floor->fieldCaret()];
    yield 'progress spinner' => [$floor->progressSpinner(0)];
    yield 'progress track' => [$floor->progressTrack(4, 10)];
  }

  public function testMarkOnlyAppearsWhereThereIsSomethingToMark(): void {
    $floor = new FloorTheme();

    $this->assertSame(' ', $floor->fieldSelector(FALSE));
    $this->assertSame(' ', $floor->fieldEntrySelector(FALSE));
    $this->assertNotSame($floor->fieldEntryMarker(TRUE), $floor->fieldEntryMarker(FALSE));
  }

  public function testEntryIsItsOwnTextAndTheMarkBesideItIsNot(): void {
    // Selecting and marking come apart at the floor too: the entry says what
    // it is, and the mark beside it says whether it was picked.
    $floor = new FloorTheme();

    $this->assertSame('Apple', $floor->fieldEntry('Apple', TRUE));
    $this->assertSame('Apple', $floor->fieldEntry('Apple', FALSE));
  }

  public function testFramingButtonBelongsToTheElementRatherThanTheBlock(): void {
    $floor = new FloorTheme();

    $this->assertSame('[ Submit ]', $floor->actionButton('Submit'));
    $this->assertSame('[ Submit ]', $floor->actionSelected('Submit'));
    $this->assertSame('  ', $floor->actionSeparator());
  }

  public function testSeparatorsAndTalliesReadWithoutAnythingToDrawThemWith(): void {
    $floor = new FloorTheme();

    $this->assertSame(', ', $floor->fieldValueSeparator());
    $this->assertSame('4/10', $floor->progressCount(4, 10));
  }

  public function testSpinnerCyclesItsOwnFramesAndTrackFillsInProportion(): void {
    $floor = new FloorTheme();

    $this->assertSame($floor->progressSpinner(0), $floor->progressSpinner(4));
    $this->assertSame('[####------]', $floor->progressTrack(4, 10));
    $this->assertSame('[##########]', $floor->progressTrack(99, 10));
    $this->assertSame('[----------]', $floor->progressTrack(-1, 10));
  }

}
