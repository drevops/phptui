<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\DateBounds;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Weekday;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\StepCapableInterface;

/**
 * A navigable month calendar returning a normalized ISO `Y-m-d` string.
 *
 * The move actions - bound to the arrow keys by default, and to h/j/k/l too
 * under the vim preset - move the cursor by day and by week; the page keys
 * change month, and Home/End jump to the first/last day of the visible month.
 * Every motion is clamped to the declared min/max range, so the cursor never
 * settles on an out-of-range day; days outside the range stay visible but
 * dimmed.
 *
 * @package DrevOps\Tui\Widget
 */
class CalendarWidget extends AbstractWidget implements StepCapableInterface {

  /**
   * The visible width of the seven-column grid, at four columns per day.
   */
  protected const int GRID_WIDTH = 28;

  /**
   * An empty four-column calendar cell.
   */
  protected const string BLANK_CELL = '    ';

  /**
   * The min/max range and the week-start day.
   */
  protected DateBounds $bounds;

  /**
   * The currently highlighted date, always within the bounds.
   */
  protected \DateTimeImmutable $cursor;

  /**
   * Construct a calendar widget.
   *
   * @param string $value
   *   The initial date as an ISO `Y-m-d` string; empty opens on today.
   * @param \DrevOps\Tui\Model\DateBounds|null $bounds
   *   Optional min/max range and week-start day; NULL for an open range that
   *   starts the week on Monday.
   */
  public function __construct(string $value = '', ?DateBounds $bounds = NULL) {
    $this->bounds = $bounds ?? new DateBounds();
    $seed = DateBounds::parse($value) ?? new \DateTimeImmutable('today');
    $this->cursor = $this->bounds->clamp($seed);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Calendar);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    $moved = $this->move($key, $keys);
    if ($moved instanceof \DateTimeImmutable) {
      $this->cursor = $this->bounds->clamp($moved);
    }
  }

  /**
   * The date a navigation key moves to before clamping, or NULL for no move.
   *
   * Day and week movement resolve through the injected key bindings, so the
   * arrow keys, the vim preset and any consumer remap all reach them; the month
   * and month-edge jumps have no action of their own and stay on their keys.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to interpret.
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The resolved bindings for this widget's scope.
   *
   * @return \DateTimeImmutable|null
   *   The unclamped target date, or NULL when the key does not navigate.
   */
  protected function move(Key $key, ScopedKeyMap $keys): ?\DateTimeImmutable {
    return match (TRUE) {
      $keys->matches($key, Action::MoveLeft) => $this->cursor->modify('-1 day'),
      $keys->matches($key, Action::MoveRight) => $this->cursor->modify('+1 day'),
      $keys->matches($key, Action::MoveUp) => $this->cursor->modify('-7 days'),
      $keys->matches($key, Action::MoveDown) => $this->cursor->modify('+7 days'),
      $key->is(KeyName::PageUp) => $this->shiftMonths(-1),
      $key->is(KeyName::PageDown) => $this->shiftMonths(1),
      $key->is(KeyName::Home) => $this->cursor->modify('first day of this month'),
      $key->is(KeyName::End) => $this->cursor->modify('last day of this month'),
      default => NULL,
    };
  }

  /**
   * The cursor moved by whole months, kept on a valid day-of-month.
   *
   * Anchoring on the first of the month before shifting avoids the day-of-month
   * overflow that a naive "+1 month" produces (e.g. Jan 31 becoming Mar 3); the
   * day is then re-applied, capped to the shorter month's length.
   *
   * @param int $months
   *   The signed number of months to move.
   *
   * @return \DateTimeImmutable
   *   The shifted date.
   */
  protected function shiftMonths(int $months): \DateTimeImmutable {
    $day = (int) $this->cursor->format('j');
    $first = $this->cursor->modify('first day of this month')->modify(sprintf('%+d months', $months));

    return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, (int) $first->format('t')));
  }

  /**
   * {@inheritdoc}
   *
   * Each position is one day, clamped to the declared range.
   */
  public function stepBy(int $delta): void {
    $this->cursor = $this->bounds->clamp($this->cursor->modify(sprintf('%+d days', $delta)));
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    return $this->cursor->format('Y-m-d');
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $rows = array_merge([$this->heading($theme), $this->weekdayRow($theme)], $this->weekRows($theme));

    return implode("\n", $rows);
  }

  /**
   * {@inheritdoc}
   *
   * Month (PgUp/PgDn) and month-edge (Home/End) jumps have no action of their
   * own, so the footer advertises the binding-driven day/week motion.
   */
  #[\Override]
  public function hints(): array {
    return [
      new Hint('day', Action::MoveLeft, Action::MoveRight),
      new Hint('week', Action::MoveUp, Action::MoveDown),
      ...parent::hints(),
    ];
  }

  /**
   * The centered "Month YYYY" heading over the calendar grid.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The themed, centered heading.
   */
  protected function heading(ThemeInterface $theme): string {
    $title = Translator::t($this->cursor->format('F')) . ' ' . $this->cursor->format('Y');
    $left = max(0, intdiv(self::GRID_WIDTH - Strings::length($title), 2));

    return str_repeat(' ', $left) . $theme->title($title);
  }

  /**
   * The weekday heading row, ordered from the configured week-start day.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The themed weekday row.
   */
  protected function weekdayRow(ThemeInterface $theme): string {
    $cells = array_map(static fn(Weekday $day): string => sprintf(' %2s ', $day->abbreviation()), $this->bounds->weekStart->sequence());

    return $theme->footer(implode('', $cells));
  }

  /**
   * The calendar grid rows for the visible month.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return list<string>
   *   One string per week row.
   */
  protected function weekRows(ThemeInterface $theme): array {
    $first = $this->cursor->modify('first day of this month');
    $days = (int) $this->cursor->format('t');
    $lead = $this->bounds->weekStart->columnOf(Weekday::fromDate($first));

    $cells = array_fill(0, $lead, self::BLANK_CELL);
    for ($day = 1; $day <= $days; $day++) {
      $cells[] = $this->dayCell($theme, $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day), $day);
    }

    $rows = [];
    foreach (array_chunk($cells, 7) as $week) {
      $rows[] = implode('', array_pad($week, 7, self::BLANK_CELL));
    }

    return $rows;
  }

  /**
   * Render one day cell: bracketed at the cursor, dimmed when out of range.
   *
   * The cursor cell carries literal brackets so it stays distinguishable even
   * with colour off, mirroring how the radio glyph marks a selection in ASCII.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DateTimeImmutable $date
   *   The cell's date.
   * @param int $day
   *   The day-of-month number.
   *
   * @return string
   *   The four-column themed cell.
   */
  protected function dayCell(ThemeInterface $theme, \DateTimeImmutable $date, int $day): string {
    if ($date->format('Y-m-d') === $this->cursor->format('Y-m-d')) {
      return $theme->highlight(sprintf('[%2d]', $day));
    }

    $cell = sprintf(' %2d ', $day);

    return $this->bounds->contains($date) ? $cell : $theme->description($cell);
  }

}
