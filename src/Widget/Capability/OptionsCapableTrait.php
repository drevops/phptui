<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Shared option-list behaviour for the choice widgets.
 *
 * Holds the ordered option rows and centralizes the two things every choice
 * widget must agree on: the cursor only ever rests on a selectable row (so
 * separators, headings and disabled options are skipped), and those
 * non-selectable rows render as visual-only structure.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait OptionsCapableTrait {

  /**
   * The option rows in display order.
   *
   * @var list<\DrevOps\Tui\Model\Option>
   */
  protected array $options = [];

  /**
   * Normalize and store the option rows.
   *
   * @param array<int|string,\DrevOps\Tui\Model\Option|string> $options
   *   A list of options or the value => label shorthand map.
   */
  protected function initOptions(array $options): void {
    $this->options = Option::list($options);
  }

  /**
   * The index of the first selectable row, or 0 when none is selectable.
   *
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows to scan.
   *
   * @return int
   *   The first selectable index.
   */
  protected function firstSelectable(array $rows): int {
    foreach ($rows as $index => $row) {
      if ($row->selectable()) {
        return $index;
      }
    }

    return 0;
  }

  /**
   * The cursor for a default value: its selectable row, else the first one.
   *
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows to scan.
   * @param string $default
   *   The default value to land on.
   *
   * @return int
   *   The resolved cursor index.
   */
  protected function cursorForDefault(array $rows, string $default): int {
    foreach ($rows as $index => $row) {
      if ($row->selectable() && $row->value === $default) {
        return $index;
      }
    }

    return $this->firstSelectable($rows);
  }

  /**
   * Step the cursor to the next selectable row, skipping non-selectable rows.
   *
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows to move over.
   * @param int $from
   *   The current cursor index.
   * @param int $dir
   *   The direction: +1 down, -1 up.
   *
   * @return int
   *   The next selectable index, or $from when none lies in that direction.
   */
  protected function stepCursor(array $rows, int $from, int $dir): int {
    $index = $from + $dir;

    while ($index >= 0 && $index < count($rows)) {
      if ($rows[$index]->selectable()) {
        return $index;
      }

      $index += $dir;
    }

    return $from;
  }

  /**
   * The rows the widget currently shows.
   *
   * @return list<\DrevOps\Tui\Model\Option>
   *   The visible rows.
   */
  abstract public function visible(): array;

  /**
   * Render one option row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  abstract public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string;

  /**
   * Render the visible rows as the widget's paged option-list body.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The list body: one line per visible row, scroll-wrapped.
   */
  protected function renderChoiceList(ThemeInterface $theme): string {
    $visible = $this->visible();
    $viewport = $this->pageViewport(count($visible), $this->cursor);

    return implode("\n", $this->renderListRows($theme, $visible, $viewport, fn(Option $option, int $index): string => $this->renderOptionRow($theme, $option, $index === $this->cursor)));
  }

  /**
   * The description of the highlighted option, empty when none is highlighted.
   *
   * @return string
   *   The highlighted option's description.
   */
  protected function highlightedDescription(): string {
    $option = $this->visible()[$this->cursor] ?? NULL;

    return $option instanceof Option && $option->selectable() ? $option->description : '';
  }

  /**
   * Render the visible option rows, dispatching structure rows centrally.
   *
   * Headings and separators render identically in every choice widget; the
   * closure renders an option row (including its disabled state), receiving
   * the option and its absolute index within the rows.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   The rows the widget currently shows.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The paging window over the rows.
   * @param \Closure $render
   *   Renders one option row: `fn (Option $option, int $index): string`.
   *
   * @return list<string>
   *   The rendered lines, wrapped with the scroll indicators.
   */
  protected function renderListRows(ThemeInterface $theme, array $rows, Viewport $viewport, \Closure $render): array {
    $lines = [];

    foreach (array_slice($rows, $viewport->offset, $this->pageSize) as $slot => $option) {
      if ($option->kind === OptionKind::Heading) {
        $lines[] = $this->renderHeadingRow($theme, $option);

        continue;
      }

      if ($option->kind === OptionKind::Separator) {
        $lines[] = $this->renderSeparatorRow($theme);

        continue;
      }

      $lines[] = $render($option, $viewport->offset + $slot);
    }

    return $this->wrapScrolled($theme, $lines, $viewport);
  }

  /**
   * Render a group heading row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $option
   *   The heading row.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderHeadingRow(ThemeInterface $theme, Option $option): string {
    return $theme->heading($option->label);
  }

  /**
   * Render a separator row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderSeparatorRow(ThemeInterface $theme): string {
    return $theme->divider();
  }

  /**
   * Render a disabled option's label, appending its reason when present.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Model\Option $option
   *   The disabled option row.
   *
   * @return string
   *   The dimmed label (with reason).
   */
  protected function renderDisabledLabel(ThemeInterface $theme, Option $option): string {
    $text = $option->label;

    if ($option->disabledReason !== '') {
      $text .= ' (' . $option->disabledReason . ')';
    }

    return $theme->disabled($text);
  }

}
