<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

use DrevOps\PhpTui\Block\Option;
use DrevOps\PhpTui\Block\OptionType;
use DrevOps\PhpTui\Screen\Viewport;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * Shared option-list behaviour for the choice fields.
 *
 * Holds the ordered option rows and the two invariants every choice field
 * shares. Where a selectable row exists the cursor is placed on one, skipping
 * separators, headings and disabled options; where none is selectable it
 * falls back to index 0. Non-selectable rows render as visual-only structure.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
trait OptionsCapableTrait {

  /**
   * The option rows in display order.
   *
   * @var list<\DrevOps\PhpTui\Block\Option>
   */
  protected array $options = [];

  /**
   * Normalize and store the option rows.
   *
   * @param array<int|string,\DrevOps\PhpTui\Block\Option|string> $options
   *   A list of options or the value => label shorthand map.
   */
  protected function initOptions(array $options): void {
    $this->options = Option::list($options);
  }

  /**
   * The index of the first selectable row, or 0 when none is selectable.
   *
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows to scan.
   *
   * @return int
   *   The first selectable index.
   */
  protected function firstSelectable(array $rows): int {
    foreach ($rows as $index => $row) {
      if ($row->isSelectable()) {
        return $index;
      }
    }

    return 0;
  }

  /**
   * The cursor for a default value: its selectable row, else the first one.
   *
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows to scan.
   * @param string $default
   *   The default value to land on.
   *
   * @return int
   *   The resolved cursor index.
   */
  protected function cursorForDefault(array $rows, string $default): int {
    foreach ($rows as $index => $row) {
      if ($row->isSelectable() && $row->value === $default) {
        return $index;
      }
    }

    return $this->firstSelectable($rows);
  }

  /**
   * Step the cursor to the next selectable row, skipping non-selectable rows.
   *
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
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
      if ($rows[$index]->isSelectable()) {
        return $index;
      }

      $index += $dir;
    }

    return $from;
  }

  /**
   * The rows the field currently shows.
   *
   * @return list<\DrevOps\PhpTui\Block\Option>
   *   The visible rows.
   */
  abstract public function visible(): array;

  /**
   * Render one option row.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\PhpTui\Block\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  abstract public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string;

  /**
   * Render the visible rows as the field's paged option-list body.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
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
  protected function currentDescription(): string {
    $option = $this->visible()[$this->cursor] ?? NULL;

    return $option instanceof Option && $option->isSelectable() ? $option->description : '';
  }

  /**
   * Render the visible option rows, dispatching structure rows centrally.
   *
   * Headings and separators render identically in every choice field; the
   * closure renders an option row (including its disabled state), receiving
   * the option and its absolute index within the rows.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param list<\DrevOps\PhpTui\Block\Option> $rows
   *   The rows the field currently shows.
   * @param \DrevOps\PhpTui\Screen\Viewport $viewport
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
      if ($option->kind === OptionType::Heading) {
        $lines[] = $this->renderHeadingRow($theme, $option);

        continue;
      }

      if ($option->kind === OptionType::Separator) {
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
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\PhpTui\Block\Option $option
   *   The heading row.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderHeadingRow(ThemeInterface $theme, Option $option): string {
    return $this->elements($theme)->fieldCaption($option->label);
  }

  /**
   * Render a separator row.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderSeparatorRow(ThemeInterface $theme): string {
    return $this->elements($theme)->fieldOptionSeparator();
  }

  /**
   * Render a disabled option's label, appending its reason when present.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\PhpTui\Block\Option $option
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

    return $this->elements($theme)->fieldOptionNote($text);
  }

}
