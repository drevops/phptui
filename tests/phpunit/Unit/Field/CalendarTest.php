<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Calendar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the calendar field.
 */
#[CoversClass(Calendar::class)]
#[CoversClass(AbstractField::class)]
#[Group('field')]
final class CalendarTest extends TestCase {

  public function testSeedsFromValue(): void {
    $field = new Calendar('2026-07-15');

    $this->assertSame('2026-07-15', $field->value());
  }

  public function testOpensOnTodayWhenEmpty(): void {
    $field = new Calendar();

    $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $field->value());
  }

  public function testInvalidSeedFallsBackToToday(): void {
    $field = new Calendar('not-a-date');

    $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $field->value());
  }

  #[DataProvider('dataProviderNavigation')]
  public function testNavigation(Key $key, string $expected): void {
    $field = new Calendar('2026-07-15');

    $field->handle($key);

    $this->assertSame($expected, $field->value());
  }

  public static function dataProviderNavigation(): \Iterator {
    yield 'left is previous day' => [Key::named(KeyName::Left), '2026-07-14'];
    yield 'right is next day' => [Key::named(KeyName::Right), '2026-07-16'];
    yield 'up is previous week' => [Key::named(KeyName::Up), '2026-07-08'];
    yield 'down is next week' => [Key::named(KeyName::Down), '2026-07-22'];
    yield 'page up is previous month' => [Key::named(KeyName::PageUp), '2026-06-15'];
    yield 'page down is next month' => [Key::named(KeyName::PageDown), '2026-08-15'];
    yield 'home is first of month' => [Key::named(KeyName::Home), '2026-07-01'];
    yield 'end is last of month' => [Key::named(KeyName::End), '2026-07-31'];
  }

  #[DataProvider('dataProviderVimNavigation')]
  public function testVimNavigation(Key $key, string $expected): void {
    // Injecting the vim scope map proves day and week movement resolve through
    // the key bindings: the vim preset reaches the same moves via h/j/k/l.
    $field = (new Calendar('2026-07-15'))->setKeys(KeyMapManager::create('vim')->forField(FieldType::Calendar));

    $field->handle($key);

    $this->assertSame($expected, $field->value());
  }

  public static function dataProviderVimNavigation(): \Iterator {
    yield 'h is previous day' => [Key::char('h'), '2026-07-14'];
    yield 'l is next day' => [Key::char('l'), '2026-07-16'];
    yield 'k is previous week' => [Key::char('k'), '2026-07-08'];
    yield 'j is next week' => [Key::char('j'), '2026-07-22'];
  }

  #[DataProvider('dataProviderPageMonthClampsToShortMonth')]
  public function testPageMonthClampsToShortMonth(string $seed, Key $key, string $expected): void {
    $field = new Calendar($seed);

    $field->handle($key);

    $this->assertSame($expected, $field->value());
  }

  public static function dataProviderPageMonthClampsToShortMonth(): \Iterator {
    // Jan 31 has no counterpart in the shorter month, so the day caps to
    // that month's end.
    yield 'jan 31 to non-leap feb' => ['2026-01-31', Key::named(KeyName::PageDown), '2026-02-28'];
    yield 'jan 31 to leap feb' => ['2024-01-31', Key::named(KeyName::PageDown), '2024-02-29'];
    yield 'mar 31 back to feb' => ['2026-03-31', Key::named(KeyName::PageUp), '2026-02-28'];
    yield 'oct 31 to nov' => ['2026-10-31', Key::named(KeyName::PageDown), '2026-11-30'];
  }

  public function testUnhandledKeysAreNoOps(): void {
    $field = new Calendar('2026-07-15');

    // An unmapped character and an unmapped named key both leave the cursor
    // in place.
    $field->handle(Key::char('z'));
    $field->handle(Key::named(KeyName::Tab));

    $this->assertSame('2026-07-15', $field->value());
  }

  public function testNavigationClampsWithinBounds(): void {
    $bounds = new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20'));
    $field = new Calendar('2026-07-11', bounds: $bounds);

    // A week back would land before the minimum, so it clamps to the minimum.
    $field->handle(Key::named(KeyName::Up));
    $this->assertSame('2026-07-10', $field->value());

    // Already on the minimum, a further step left stays on it.
    $field->handle(Key::named(KeyName::Left));
    $this->assertSame('2026-07-10', $field->value());

    // The end of the month is past the maximum, so it clamps to the maximum.
    $field->handle(Key::named(KeyName::End));
    $this->assertSame('2026-07-20', $field->value());
  }

  public function testStepByMovesByDaysClampedToBounds(): void {
    $bounds = new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20'));
    $field = new Calendar('2026-07-15', bounds: $bounds);

    $field->stepBy(3);
    $this->assertSame('2026-07-18', $field->value());

    $field->stepBy(-30);
    $this->assertSame('2026-07-10', $field->value());
  }

  public function testConstructionClampsSeedIntoBounds(): void {
    $bounds = new DateBounds(new \DateTimeImmutable('2026-07-10'), new \DateTimeImmutable('2026-07-20'));

    $this->assertSame('2026-07-10', (new Calendar('2026-07-01', bounds: $bounds))->value());
    $this->assertSame('2026-07-20', (new Calendar('2026-07-31', bounds: $bounds))->value());
  }

  public function testAcceptReturnsIsoDate(): void {
    $field = new Calendar('2026-07-15');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame('2026-07-16', $value);
    $this->assertTrue($field->isComplete());
  }

  public function testCancel(): void {
    $field = new Calendar('2026-07-15');

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testValidatorErrorIsShown(): void {
    $field = (new Calendar('2026-07-15'))->setHandlers(validate: static fn(mixed $value): string => 'No dates allowed.');

    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertStringContainsString('No dates allowed.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testRendersCalendar(): void {
    $field = new Calendar('2026-07-15');

    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('July 2026', $view);
    // The cursor day is bracketed.
    $this->assertStringContainsString('[15]', $view);
    // The weekday header defaults to a Monday-first week.
    $this->assertMatchesRegularExpression('/Mo\s+Tu\s+We\s+Th\s+Fr\s+Sa\s+Su/', $view);
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Calendar('2026-07-15'))->hints());

    $this->assertSame(['move by day', 'move by week', 'accept', 'cancel'], $labels);
  }

  public function testWeekStartRotatesHeaderAndLayout(): void {
    $sunday = Ansi::strip((new Calendar('2026-07-15', bounds: new DateBounds(weekStart: Weekday::Sunday)))->view(new DefaultTheme()));

    // A Sunday-first week reorders the weekday header.
    $this->assertMatchesRegularExpression('/Su\s+Mo\s+Tu\s+We\s+Th\s+Fr\s+Sa/', $sunday);

    // July 1, 2026 is a Wednesday. Starting the week on Sunday shifts the
    // month one column right, so the first row holds only days 1-4 (through
    // Saturday) and day 5 (Sunday) starts the next row. The default
    // Monday-first week fits days 1-5 in the first row. The first grid row is
    // the third rendered line.
    $monday = Ansi::strip((new Calendar('2026-07-15'))->view(new DefaultTheme()));
    $this->assertStringContainsString('5', explode("\n", $monday)[2]);
    $this->assertStringNotContainsString('5', explode("\n", $sunday)[2]);
  }

  public function testAsciiRendering(): void {
    $field = new Calendar('2026-07-15');
    $theme = new DefaultTheme(76, ['unicode' => FALSE, 'color' => FALSE]);

    $view = $field->view($theme);

    $this->assertStringContainsString('July 2026', $view);
    // The bracket keeps the cursor day distinguishable without colour.
    $this->assertStringContainsString('[15]', $view);
  }

  public function testDimsOutOfRangeDays(): void {
    $bounds = new DateBounds(new \DateTimeImmutable('2026-07-10'));
    $field = new Calendar('2026-07-15', bounds: $bounds);
    $theme = new DefaultTheme();

    $view = $field->view($theme);

    // A day before the minimum is rendered dimmed, not plain.
    $this->assertStringContainsString($theme->fieldEntryNote(sprintf(' %2d ', 5)), $view);
    // The cursor day stays bracketed and highlighted.
    $this->assertStringContainsString($theme->fieldEntry('[15]', FALSE, TRUE), $view);
  }

  public function testDimsDaysPastMaximum(): void {
    $bounds = new DateBounds(max: new \DateTimeImmutable('2026-07-20'));
    $field = new Calendar('2026-07-15', bounds: $bounds);
    $theme = new DefaultTheme();

    $view = $field->view($theme);

    // A day after the maximum is dimmed too, guarding the upper bound.
    $this->assertStringContainsString($theme->fieldEntryNote(sprintf(' %2d ', 25)), $view);
  }

}
