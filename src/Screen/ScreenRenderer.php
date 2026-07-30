<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Draws a screen, outward from the root.
 *
 * Each step hands down exactly one thing and knows nothing of the step after
 * it: the screen gives the layout its space, the layout works out a size for
 * each region, the region flows its blocks into that size, and a block reaches
 * the theme for elements. Nothing reaches back up.
 *
 * @package DrevOps\Tui\Screen
 */
final class ScreenRenderer {

  /**
   * Construct a renderer.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme every block draws through.
   */
  public function __construct(
    protected ThemeInterface $theme,
  ) {
  }

  /**
   * Draw a screen.
   *
   * @param \DrevOps\Tui\Screen\Screen $screen
   *   The screen.
   * @param int $rows
   *   The terminal rows.
   * @param int $columns
   *   The terminal columns.
   *
   * @return string
   *   The frame; newlines separate rows.
   */
  public function render(Screen $screen, int $rows, int $columns): string {
    return implode("\n", $this->lay($screen->currentLayout(), $rows, $columns));
  }

  /**
   * Draw a layout into a space.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   * @param int $rows
   *   The rows it may fill.
   * @param int $columns
   *   The columns it may fill.
   *
   * @return list<string>
   *   The rows.
   */
  protected function lay(LayoutInterface $layout, int $rows, int $columns): array {
    $names = $layout->names();

    if ($names === []) {
      return [];
    }

    $down = $layout->axis() === Axis::Rows;
    $sizes = $layout->arrange($down ? $rows : $columns);

    // A rows layout stacks its regions; a columns layout draws each into its
    // own width and then pastes them side by side onto shared rows.
    if ($down) {
      $out = [];

      foreach ($sizes as $name => $size) {
        foreach ($this->fill($layout->in($name), $size, $columns) as $line) {
          $out[] = $line;
        }
      }

      return $out;
    }

    $columns_out = [];

    foreach ($sizes as $name => $size) {
      $columns_out[] = $this->fill($layout->in($name), $rows, $size);
    }

    return $this->paste($columns_out, $sizes, $rows);
  }

  /**
   * Draw a region's blocks into the space it was given.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param int $rows
   *   The rows it was given.
   * @param int $columns
   *   The columns it was given.
   *
   * @return list<string>
   *   Exactly $rows rows, padded or clipped to fit.
   */
  protected function fill(Region $region, int $rows, int $columns): array {
    $drawn = [];

    foreach ($region->blocks() as $block) {
      // An entered panel is where the next layout starts, which is where depth
      // comes from rather than a fifth level. Only the renderer knows the box
      // it has to fit into, so the recursion happens here.
      $drawn[] = $block instanceof Panel && $block->isEntered()
        ? implode("\n", $this->lay($block->currentLayout(), $rows, $columns))
        : $block->render($this->theme);
    }

    $lines = $region->flowAxis() === Axis::Columns
      ? $this->across($drawn)
      : $this->down($drawn);

    // Its contents are its own problem once it has a size: it scrolls if it was
    // declared to, and clips if it was not. Either way it hands back the rows
    // it was given, so the frame stays the shape the layout worked out.
    $from = $region->isScrolling() ? $region->offset(count($lines), $rows) : 0;
    $lines = array_slice($lines, $from, max(0, $rows));

    while (count($lines) < $rows) {
      $lines[] = '';
    }

    return array_map(static fn(string $line): string => Box::fit($line, $columns), $lines);
  }

  /**
   * Stack rendered blocks down a region.
   *
   * @param list<string> $drawn
   *   The rendered blocks.
   *
   * @return list<string>
   *   The rows.
   */
  protected function down(array $drawn): array {
    $lines = [];

    foreach ($drawn as $block) {
      foreach (explode("\n", $block) as $line) {
        $lines[] = $line;
      }
    }

    return $lines;
  }

  /**
   * Run rendered blocks across a region.
   *
   * @param list<string> $drawn
   *   The rendered blocks.
   *
   * @return list<string>
   *   The rows.
   */
  protected function across(array $drawn): array {
    $blocks = array_map(static fn(string $block): array => explode("\n", $block), $drawn);
    $widths = array_map(static fn(array $lines): int => max(array_map(Ansi::width(...), $lines)), $blocks);
    $height = $blocks === [] ? 0 : max(array_map(count(...), $blocks));
    $lines = [];

    for ($row = 0; $row < $height; $row++) {
      $parts = [];

      foreach ($blocks as $index => $block) {
        $parts[] = Box::fit($block[$row] ?? '', $widths[$index]);
      }

      $lines[] = rtrim(implode(' ', $parts));
    }

    return $lines;
  }

  /**
   * Paste columns side by side onto shared rows.
   *
   * @param list<list<string>> $columns
   *   Each column's rows.
   * @param array<string,int> $widths
   *   Each column's width, in declaration order.
   * @param int $rows
   *   The rows to produce.
   *
   * @return list<string>
   *   The rows.
   */
  protected function paste(array $columns, array $widths, int $rows): array {
    $widths = array_values($widths);
    $out = [];

    for ($row = 0; $row < $rows; $row++) {
      $parts = [];

      foreach ($columns as $index => $column) {
        $parts[] = Box::fit($column[$row] ?? '', $widths[$index]);
      }

      $out[] = implode('', $parts);
    }

    return $out;
  }

}
