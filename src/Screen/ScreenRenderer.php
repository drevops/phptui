<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
use DrevOps\Tui\Theme\Capability\UnicodeCapableInterface;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Draws a screen, outward from the root.
 *
 * Each step hands down exactly one thing and knows nothing of the step after
 * it: the screen gives the layout its space, the layout works out a size for
 * each region, the region flows its blocks into that size, and a block reaches
 * the theme for elements. Nothing reaches back up.
 *
 * Three things are drawn here rather than by any block: the frame around every
 * region at once, the mark saying a region's contents outran it, and the air
 * the theme asks for between one block and the next. None is anything a block
 * could ask for, because a block only ever fills the space it is given and
 * never learns where that space ends or what is drawn beside it.
 *
 * @package DrevOps\Tui\Screen
 */
final class ScreenRenderer {

  /**
   * Construct a renderer.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme every block draws through.
   * @param \DrevOps\Tui\Theme\Border $border
   *   The frame drawn around every region at once; none by default, which
   *   leaves the rows exactly as the layout arranged them.
   */
  public function __construct(
    protected ThemeInterface $theme,
    protected Border $border = Border::None,
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
    if ($this->border === Border::None) {
      return implode("\n", $this->lay($screen->currentLayout(), $rows, $columns));
    }

    // A frame spends a rule top and bottom, and a border column plus a gutter
    // each side, so what the layout is given is the terminal less its chrome.
    $inside = $this->lay($screen->currentLayout(), max(0, $rows - 2), max(1, $columns - 4));

    return implode("\n", $this->framed($inside, $columns));
  }

  /**
   * The rows a region's blocks come to, and which one a block starts on.
   *
   * The same walk a frame makes, counted rather than drawn: a region that
   * scrolls is moved against these numbers, so what a driver measures and what
   * a reader sees are worked out by one rule rather than by two free to drift
   * apart. It measures a region's own blocks, which is what the panel you are
   * in holds - going into a panel is where a layout starts rather than where a
   * row is counted.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param \DrevOps\Tui\Block\BlockInterface|null $of
   *   The block to locate, if one is being looked for.
   *
   * @return array{int,int}
   *   The rows its blocks come to, and the first row of the given block - or
   *   -1 when it holds no such block.
   */
  public function extent(Region $region, ?BlockInterface $of = NULL): array {
    $blocks = $region->blocks();
    $total = 0;
    $row = -1;
    $spaced = $this->spaced();

    foreach ($this->rendered($blocks) as $index => $rendered) {
      // Every block that draws at all costs a row, so anything past zero is a
      // block with air owed above it.
      if ($spaced && $total > 0) {
        $total++;
      }

      if ($blocks[$index] === $of) {
        $row = $total;
      }

      $total += substr_count($rendered, "\n") + 1;
    }

    return [$total, $row];
  }

  /**
   * Wrap laid-out rows in the frame that surrounds every region at once.
   *
   * @param list<string> $lines
   *   The rows as the layout arranged them.
   * @param int $columns
   *   The columns the frame spans, including its own.
   *
   * @return list<string>
   *   The framed rows.
   */
  protected function framed(array $lines, int $columns): array {
    $chrome = $this->chrome();
    $chars = Box::chars($this->border, $this->unicode());
    $inner = max(1, $columns - 4);
    $bar = $chrome->chromeBorder($chars['v']);

    $out = [$chrome->chromeBorder(Box::rule($chars['tl'], $chars['tr'], $chars['h'], $columns))];

    foreach ($lines as $line) {
      $out[] = $bar . ' ' . Box::fit($line, $inner) . ' ' . $bar;
    }

    $out[] = $chrome->chromeBorder(Box::rule($chars['bl'], $chars['br'], $chars['h'], $columns));

    return $out;
  }

  /**
   * The theme, narrowed to the elements the window chrome composes.
   *
   * @return \DrevOps\Tui\Block\Element\ChromeElementsInterface
   *   The theme, able to draw the chrome.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   */
  protected function chrome(): ChromeElementsInterface {
    if (!$this->theme instanceof ChromeElementsInterface) {
      $elements = ChromeElementsInterface::class;

      throw new \InvalidArgumentException(sprintf('%s cannot draw the window chrome: it does not implement %s.', $this->theme::class, $elements));
    }

    return $this->theme;
  }

  /**
   * Whether the frame is drawn with glyphs rather than their stand-ins.
   *
   * @return bool
   *   TRUE when the theme declared it handles them.
   */
  protected function unicode(): bool {
    return $this->theme instanceof UnicodeCapableInterface && $this->theme->hasUnicode();
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
    $drawn = $this->draw($region->blocks(), $rows, $columns);

    $lines = $region->flowAxis() === Axis::Columns
      ? $this->across($drawn)
      : $this->down($drawn);

    // Its contents are its own problem once it has a size: it scrolls if it was
    // declared to, and clips if it was not. Either way it hands back the rows
    // it was given, so the frame stays the shape the layout worked out.
    $content = count($lines);
    $from = $region->isScrolling() ? $region->offset($content, $rows) : 0;
    $lines = array_slice($lines, $from, max(0, $rows));

    while (count($lines) < $rows) {
      $lines[] = '';
    }

    $lines = array_map(static fn(string $line): string => Box::fit($line, $columns), $lines);

    // Only a region you can move through says there is more: one that clips
    // says nothing, because there is no way to reach what it dropped.
    if (!$region->isScrolling()) {
      return $lines;
    }

    return $this->marked($lines, $columns, $from > 0, $from + $rows < $content);
  }

  /**
   * Draw a region's blocks, an entered panel replacing the rest of them.
   *
   * An entered panel is where the next layout starts, which is where depth
   * comes from rather than a fifth level. Only the renderer knows the box it
   * has to fit into, so the recursion happens here.
   *
   * Going into a panel replaces the screen with its contents, so the panel
   * takes the whole region and the rows placed beside it are the ones you left
   * behind - the sibling rows of the panel you came from, and the buttons that
   * end the form, which sit among the outermost panel's own rows. Coming back
   * out draws every one of them again.
   *
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   * @param int $rows
   *   The rows the region was given.
   * @param int $columns
   *   The columns it was given.
   *
   * @return list<string>
   *   Each block as it is drawn, in the order it was added.
   */
  protected function draw(array $blocks, int $rows, int $columns): array {
    foreach ($blocks as $block) {
      if ($block instanceof Panel && $block->isEntered()) {
        return [implode("\n", $this->lay($block->currentLayout(), $rows, $columns))];
      }
    }

    return array_values($this->rendered($blocks));
  }

  /**
   * Mark a region's edges where its contents run past them.
   *
   * @param list<string> $lines
   *   The rows the region hands back.
   * @param int $columns
   *   The columns it was given.
   * @param bool $above
   *   Whether there is content above the first row.
   * @param bool $below
   *   Whether there is content below the last row.
   *
   * @return list<string>
   *   The rows, marked at whichever edges they outran.
   */
  protected function marked(array $lines, int $columns, bool $above, bool $below): array {
    if ($lines === []) {
      return $lines;
    }

    if ($above) {
      $lines[0] = $this->mark($lines[0], $columns, TRUE);
    }

    if ($below) {
      $last = count($lines) - 1;
      $lines[$last] = $this->mark($lines[$last], $columns, FALSE);
    }

    return $lines;
  }

  /**
   * Put an overflow mark at the far edge of one row.
   *
   * @param string $line
   *   The row, already fitted to the columns it was given.
   * @param int $columns
   *   The columns it was given.
   * @param bool $above
   *   Whether the content it points at is above rather than below.
   *
   * @return string
   *   The row, ending in the mark.
   */
  protected function mark(string $line, int $columns, bool $above): string {
    $marker = $this->chrome()->chromeOverflowMarker($above);
    $width = Ansi::width($marker);

    // The mark sits at the region's own edge rather than on a row of its own,
    // so saying there is more never costs a row of what there is.
    return $width >= $columns ? $line : Box::fit($line, $columns - $width) . $marker;
  }

  /**
   * Stack rendered blocks down a region, spaced as the theme asks.
   *
   * What shows between the rows a region holds is the theme's to say and the
   * flow's to do: a block draws itself and never learns what sits above or
   * below it, so the air between two of them can only be put in here.
   *
   * @param list<string> $drawn
   *   The rendered blocks.
   *
   * @return list<string>
   *   The rows.
   */
  protected function down(array $drawn): array {
    $lines = [];
    $spaced = $this->spaced();

    foreach ($drawn as $block) {
      if ($spaced && $lines !== []) {
        $lines[] = '';
      }

      foreach (explode("\n", $block) as $line) {
        $lines[] = $line;
      }
    }

    return $lines;
  }

  /**
   * Whether a blank row shows between one block in a region and the next.
   *
   * @return bool
   *   TRUE when the theme asks for the air; FALSE when the rows stack against
   *   each other, and when the theme says nothing about spacing at all.
   */
  protected function spaced(): bool {
    return $this->theme instanceof OccupyCapableInterface && $this->theme->spacing() === Spacing::Padded;
  }

  /**
   * What each block in a region drew, keyed by where it sits in it.
   *
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   *
   * @return array<int,string>
   *   What each block that drew anything drew.
   */
  protected function rendered(array $blocks): array {
    $drawn = [];

    foreach ($blocks as $index => $block) {
      // A block the answers took off the screen is not there at all: it costs
      // no row, rather than costing a blank one.
      if ($block instanceof DependCapableInterface && $block->isHidden()) {
        continue;
      }

      $rendered = $block->render($this->theme);

      // Nor is a block with nothing to say: what shows between one row and the
      // next is the flow's to decide, so a block that drew nothing must not
      // leave a blank row behind for the spacing to be added around.
      if ($rendered === '') {
        continue;
      }

      $drawn[$index] = $rendered;
    }

    return $drawn;
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
