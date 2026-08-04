<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Theme;

use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
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
  public function testEveryGlyphFallsBackToWhatAsciiCanDraw(\Closure $draw): void {
    // Nothing outside ASCII, because the floor declares no Unicode - and every
    // mark still reads, because a form has to be usable without one.
    $drawn = (string) $draw(new FloorTheme());

    $this->assertSame($drawn, preg_replace('/[^\x20-\x7E]/', '', $drawn));
  }

  public static function dataProviderEveryGlyphFallsBackToWhatAsciiCanDraw(): \Iterator {
    yield 'overflow marker above' => [static fn(FloorTheme $t): string => $t->chromeOverflowMarker(TRUE)];
    yield 'overflow marker below' => [static fn(FloorTheme $t): string => $t->chromeOverflowMarker(FALSE)];
    yield 'breadcrumb separator' => [static fn(FloorTheme $t): string => $t->breadcrumbSeparator()];
    yield 'legend separator' => [static fn(FloorTheme $t): string => $t->legendSeparator()];
    yield 'field selector' => [static fn(FloorTheme $t): string => $t->fieldSelector(TRUE)];
    yield 'field help marker' => [static fn(FloorTheme $t): string => $t->fieldHelpMarker()];
    yield 'field entry selector' => [static fn(FloorTheme $t): string => $t->fieldEntrySelector(TRUE)];
    yield 'field entry marker chosen' => [static fn(FloorTheme $t): string => $t->fieldEntryMarker(TRUE)];
    yield 'field entry marker unchosen' => [static fn(FloorTheme $t): string => $t->fieldEntryMarker(FALSE)];
    yield 'field entry marker exclusive' => [static fn(FloorTheme $t): string => $t->fieldEntryMarker(TRUE, TRUE)];
    yield 'field entry separator' => [static fn(FloorTheme $t): string => $t->fieldEntrySeparator()];
    yield 'field caret' => [static fn(FloorTheme $t): string => $t->fieldCaret()];
    yield 'field mask' => [static fn(FloorTheme $t): string => $t->fieldMask()];
    yield 'field loading' => [static fn(FloorTheme $t): string => $t->fieldLoading()];
    yield 'field scale' => [static fn(FloorTheme $t): string => $t->fieldScale(3, 1, 5, 'Fair')];
    yield 'panel selector' => [static fn(FloorTheme $t): string => $t->panelSelector(TRUE)];
    yield 'panel descend' => [static fn(FloorTheme $t): string => $t->panelDescend()];
    yield 'panel summary separator' => [static fn(FloorTheme $t): string => $t->panelSummarySeparator()];
    yield 'markup bullet' => [static fn(FloorTheme $t): string => $t->markupBullet()];
    yield 'key glyph' => [static fn(FloorTheme $t): string => $t->keyGlyph(Key::named(KeyName::Escape))];
    yield 'progress spinner' => [static fn(FloorTheme $t): string => $t->progressSpinner(0)];
    yield 'progress track' => [static fn(FloorTheme $t): string => $t->progressTrack(4, 10)];
  }

  #[DataProvider('dataProviderEveryPassageSpanHandsBackItsText')]
  public function testEveryPassageSpanHandsBackItsText(\Closure $draw): void {
    // A passage with nothing to style it is the passage, and a target nothing
    // can follow is written out rather than dropped with its styling.
    $this->assertSame('Orchard', $draw(new FloorTheme()));
  }

  public static function dataProviderEveryPassageSpanHandsBackItsText(): \Iterator {
    yield 'strong' => [static fn(FloorTheme $t): string => $t->markupStrong('Orchard')];
    yield 'emphasis' => [static fn(FloorTheme $t): string => $t->markupEmphasis('Orchard')];
    yield 'code' => [static fn(FloorTheme $t): string => $t->markupCode('Orchard')];
    yield 'panel description' => [static fn(FloorTheme $t): string => $t->panelDescription('Orchard')];
    yield 'panel summary' => [static fn(FloorTheme $t): string => $t->panelSummary('Orchard')];
    yield 'entry match' => [static fn(FloorTheme $t): string => $t->fieldEntryMatch('Orchard')];
  }

  public function testFloorWritesOutTargetItCannotFollow(): void {
    $this->assertSame('Guide (https://example.com/guide)', (new FloorTheme())->markupLink('Guide', 'https://example.com/guide'));
  }

  public function testFloorTypesTheDraftAroundTheCaretAndSuppressesTheCompletion(): void {
    // Text nobody typed reads as text somebody did without something to set it
    // apart, and the floor has nothing to set it apart with.
    $floor = new FloorTheme();

    $this->assertSame('ab|cd', $floor->fieldInput('ab', 'cd', 'ef'));
    $this->assertSame('', $floor->fieldGhost('ef'));
  }

  public function testFloorLaysEveryRowOutAgainstTheSameGutter(): void {
    // A theme that would rather draw a flat list answers with no gutter at all.
    $this->assertSame('', (new FloorTheme())->fieldIndent(2));
  }

  public function testGuidanceOpensWithMarkNothingCanStrip(): void {
    // Neither hue nor slant survives here, and a constraint sits directly under
    // an entry's own text, so a mark is the one cue left to tell them apart.
    $floor = new FloorTheme();

    $this->assertSame('> Orchard', $floor->fieldConstraint('Orchard'));
    $this->assertNotSame($floor->fieldEntryDescription('Orchard'), $floor->fieldConstraint('Orchard'));
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
