<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Element\ChromeElementsInterface;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Terminal\Box;
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
 * the theme for elements. Nothing reaches back up. The one thing that travels
 * the other way is a count: what a region's contents come to can only be known
 * where they are drawn, so it is measured here and handed to the layout, which
 * apportions the axis by it without learning that blocks exist.
 *
 * Four things are drawn here rather than by any block: the frame around every
 * region at once, the mark saying a region's contents outran it, the air the
 * theme asks for between one block and the next, and the gutter it asks for
 * between two regions drawn side by side. None is anything a block could ask
 * for, because a block only ever fills the space it is given and never learns
 * where that space ends or what is drawn beside it.
 *
 * @package DrevOps\Tui\Screen
 */
final class ScreenRenderer {

  /**
   * The columns a frame spends on its own border and gutter, both sides.
   */
  public const int CHROME = 4;

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
      return implode("\n", $this->lay($screen->currentLayout(), $rows, $columns, TRUE));
    }

    // A frame spends a rule top and bottom, and a border column plus a gutter
    // each side, so what the layout is given is the terminal less its chrome.
    $inside = $this->lay($screen->currentLayout(), max(0, $rows - 2), max(1, $columns - self::CHROME), TRUE);

    return implode("\n", $this->framed($inside, $columns));
  }

  /**
   * The rows the panel a region was entered through takes of it.
   *
   * A block takes the size it drew and the region deals with what is left, and
   * the panel you are in is no different: it takes what its own layout comes
   * to, and never more than the blocks drawn beside it leave - which is the
   * room it scrolls inside when it holds more than that.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region the panel is drawn in.
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel placed in it.
   * @param int $rows
   *   The rows the region was given.
   *
   * @return int
   *   The rows.
   */
  public function room(Region $region, Panel $panel, int $rows): int {
    // A panel you walked into is drawn where the furniture is not, so it takes
    // the region whole however much of it the furniture would have wanted.
    if ($this->deeper($panel)) {
      return $rows;
    }

    [$above, $below] = $this->beside($region, $panel);

    return $this->fit($panel, [...$above, ...$below], $rows);
  }

  /**
   * Work out how much of the axis each region of a layout gets.
   *
   * Measuring and apportioning are two jobs and this is where they meet: what
   * a region's contents come to is counted here, because here is where blocks
   * are drawn, and the layout is handed the numbers and divides the axis by
   * them. Neither has to learn the other's business.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   * @param int $available
   *   The cells to divide.
   *
   * @return array<string,int>
   *   The cells each region gets, keyed by name.
   */
  public function sizes(LayoutInterface $layout, int $available): array {
    return $layout->arrange($available, $this->measured($layout));
  }

  /**
   * The rows an arrangement comes to, and which one a block starts on.
   *
   * What {@see self::extent()} answers about one region, answered about every
   * line of an arrangement at once - which is what an arrangement that moves as
   * one surface has to be measured against.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   * @param \DrevOps\Tui\Block\BlockInterface|null $of
   *   The block to locate, if one is being looked for.
   *
   * @return array{int,int}
   *   The rows its lines come to, and the first row of the given block - or -1
   *   when no region of it holds any such block.
   */
  public function reach(LayoutInterface $layout, ?BlockInterface $of = NULL): array {
    $measured = $this->measured($layout);
    $sizes = $layout->arrange($layout->natural($measured), $measured);
    $total = 0;
    $row = -1;

    foreach ($layout->lines() as $line) {
      $held = 0;

      foreach ($line as $name) {
        [, $at] = $this->extent($layout, $name, $of);

        if ($at >= 0) {
          $row = $total + $at;
        }

        $held = max($held, $sizes[$name] ?? 0);
      }

      $total += $held;
    }

    return [$total, $row];
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
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout the region belongs to.
   * @param string $name
   *   The region name.
   * @param \DrevOps\Tui\Block\BlockInterface|null $of
   *   The block to locate, if one is being looked for.
   *
   * @return array{int,int}
   *   The rows its blocks come to, and the first row of the given block - or
   *   -1 when it holds no such block.
   */
  public function extent(LayoutInterface $layout, string $name, ?BlockInterface $of = NULL): array {
    $total = 0;
    $row = -1;
    $spaced = $this->spaced();

    foreach ($this->pieces($layout->in($name)) as $piece) {
      // Every piece that draws at all costs a row, so anything past zero is a
      // piece with air owed above it.
      if ($spaced && $total > 0) {
        $total++;
      }

      $at = array_search($of, $piece['blocks'], TRUE);

      if ($of instanceof BlockInterface && is_int($at)) {
        $row = $total + $piece['offsets'][$at];
      }

      $total += $piece['height'];
    }

    return [$total, $row];
  }

  /**
   * What a region stacks, piece by piece.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   *
   * @return list<array{height:int,blocks:list<\DrevOps\Tui\Block\BlockInterface>,offsets:list<int>}>
   *   Each piece: the rows it comes to, the blocks drawn in it, and the row
   *   each of those starts on within it.
   */
  protected function pieces(Region $region): array {
    return [
      ...$this->stacked($region->headBlocks(), $region->isPreviewing()),
      ...$this->stacked($region->tailBlocks(), $region->isPreviewing()),
    ];
  }

  /**
   * One piece per block, in the order they are drawn.
   *
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   * @param bool $previews
   *   Whether a panel among them shows what is behind it rather than a row.
   *
   * @return list<array{height:int,blocks:list<\DrevOps\Tui\Block\BlockInterface>,offsets:list<int>}>
   *   The pieces.
   */
  protected function stacked(array $blocks, bool $previews = FALSE): array {
    $pieces = [];

    foreach ($blocks as $block) {
      // The panel you are in draws its own layout in place of its row, so what
      // it comes to is what that layout comes to.
      if ($block instanceof Panel && $block->isEntered()) {
        $pieces[] = ['height' => $this->height($block->currentLayout()), 'blocks' => [$block], 'offsets' => [0]];

        continue;
      }

      $rendered = $this->rendered([$block], $previews)[0] ?? NULL;

      if ($rendered === NULL) {
        continue;
      }

      $pieces[] = [
        'height' => substr_count($rendered, "\n") + 1,
        'blocks' => [$block],
        'offsets' => [0],
      ];
    }

    return $pieces;
  }

  /**
   * The rows a layout comes to when nothing has sized it.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return int
   *   The rows.
   */
  protected function height(LayoutInterface $layout): int {
    return $layout->natural($this->measured($layout));
  }

  /**
   * The rows each of a layout's regions comes to, keyed by name.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return array<string,int>
   *   The rows.
   */
  protected function measured(LayoutInterface $layout): array {
    $measured = [];

    foreach ($layout->names() as $name) {
      $measured[$name] = $this->extent($layout, $name)[0];
    }

    return $measured;
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
    $inner = max(1, $columns - self::CHROME);
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
   * @param bool $furnished
   *   Whether its regions hold the furniture a session puts around a form,
   *   which stands beside the panel you are in rather than being replaced by
   *   it. True of a screen's own regions and of nothing else.
   *
   * @return list<string>
   *   The rows.
   */
  protected function lay(LayoutInterface $layout, int $rows, int $columns, bool $furnished = FALSE): array {
    $lines = $layout->lines();

    if ($lines === []) {
      return [];
    }

    $window = $furnished ? NULL : $this->windowed($layout);

    // A window is a way into a panel, so going in leaves behind what it was a
    // window onto: the panel takes the whole arrangement rather than the cell
    // it was previewed in.
    if ($window instanceof Panel) {
      return $this->lay($window->currentLayout(), $rows, $columns);
    }

    $down = $layout->axis() === Axis::Rows;
    $measured = $this->measured($layout);
    $moves = $down && $layout->isScrolling();

    // An arrangement that moves as one is drawn whole and then moved against
    // the space it has, exactly as a region is with the blocks it holds: every
    // line takes the cells it comes to, and what is shown of the stack is a
    // second question asked afterwards.
    $sizes = $layout->arrange($moves ? max($rows, $layout->natural($measured)) : ($down ? $rows : $columns), $measured);

    // A rows layout stacks its lines; a columns layout draws each region into
    // its own width and then pastes them side by side onto shared rows.
    if ($down) {
      $out = [];

      foreach ($lines as $line) {
        foreach ($this->abreast($layout, $this->present($line, $measured), $sizes, $columns, $furnished) as $drawn) {
          $out[] = $drawn;
        }
      }

      return $moves ? $this->moved($layout, $out, $rows, $columns) : $out;
    }

    $columns_out = [];

    foreach ($sizes as $name => $size) {
      $columns_out[] = $this->fill($layout->in($name), $rows, $size, $furnished);
    }

    return $this->paste($columns_out, $sizes, $rows);
  }

  /**
   * The regions of a line that draw anything.
   *
   * A window the answers took off the form is not there at all, so the line it
   * was on closes up around it: the windows left share the width between them,
   * rather than one of them standing in an empty slot.
   *
   * @param list<string> $line
   *   The regions drawn on the line.
   * @param array<string,int> $measured
   *   The rows each region's contents come to, keyed by name.
   *
   * @return list<string>
   *   The regions still there. A line of one is not narrowed by this: it is
   *   the whole line whether it draws anything or not.
   */
  protected function present(array $line, array $measured): array {
    if (count($line) < 2) {
      return $line;
    }

    return array_values(array_filter($line, static fn(string $name): bool => ($measured[$name] ?? 0) > 0));
  }

  /**
   * Show the part of an arrangement its space has room for.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The arrangement, which is what holds the offset.
   * @param list<string> $lines
   *   Every row it drew.
   * @param int $rows
   *   The rows it was given.
   * @param int $columns
   *   The columns it was given.
   *
   * @return list<string>
   *   The rows in sight, marked at whichever edges they outran.
   */
  protected function moved(LayoutInterface $layout, array $lines, int $rows, int $columns): array {
    $content = count($lines);
    $from = $layout->offset($content, $rows);
    $shown = array_slice($lines, $from, max(0, $rows));

    return $this->marked($shown, $columns, $from > 0, $from + $rows < $content);
  }

  /**
   * Draw the regions sharing one line of the axis, side by side.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout, which is what says how much of the line each region takes.
   * @param list<string> $names
   *   The regions drawn on it.
   * @param array<string,int> $sizes
   *   The cells of the axis each region was given, keyed by name.
   * @param int $columns
   *   The columns across the line.
   * @param bool $furnished
   *   Whether the regions hold the furniture a session puts around a form.
   *
   * @return list<string>
   *   The rows.
   */
  protected function abreast(LayoutInterface $layout, array $names, array $sizes, int $columns, bool $furnished): array {
    if ($names === []) {
      return [];
    }

    $rows = $sizes[$names[0]] ?? 0;

    if (count($names) === 1) {
      return $this->fill($layout->in($names[0]), $rows, $columns, $furnished);
    }

    $width = $layout->share($columns, count($names), $this->chrome());
    $cells = [];

    foreach ($names as $name) {
      $cells[] = $this->fill($layout->in($name), $rows, $width, $furnished);
    }

    $gutter = str_repeat(' ', max(0, $this->chrome()->chromeGutter()));
    $lines = [];

    for ($row = 0; $row < $rows; $row++) {
      $parts = array_map(static fn(array $cell): string => $cell[$row] ?? '', $cells);

      // The gutters can outgrow a tiny frame even at one-column cells, so the
      // assembled row is clamped to the line as a whole.
      $lines[] = Box::fit(implode($gutter, $parts), $columns);
    }

    return $lines;
  }

  /**
   * The panel a window of an arrangement was gone into, if one was.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return \DrevOps\Tui\Block\Panel|null
   *   The panel, or NULL when no window of it has been gone into.
   */
  protected function windowed(LayoutInterface $layout): ?Panel {
    foreach ($layout->names() as $name) {
      $region = $layout->in($name);

      if (!$region->isPreviewing()) {
        continue;
      }

      $entered = $this->entered($region);

      if ($entered instanceof Panel) {
        return $entered;
      }
    }

    return NULL;
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
   * @param bool $furnished
   *   Whether it holds the furniture a session puts around a form.
   *
   * @return list<string>
   *   Exactly $rows rows, padded or clipped to fit.
   */
  protected function fill(Region $region, int $rows, int $columns, bool $furnished = FALSE): array {
    $lines = $this->arrange($region, $rows, $columns, $furnished);
    $tail = $this->tailed($region);

    // Its contents are its own problem once it has a size: it scrolls if it was
    // declared to, and clips if it was not. Either way it hands back the rows
    // it was given, so the frame stays the shape the layout worked out.
    $content = count($lines);
    $from = $region->isScrolling() ? $region->offset($content, $rows) : 0;
    $lines = array_slice($lines, $from, max(0, $rows));

    // What packs from the end takes the cells the start left, so where the two
    // meet in the middle the head keeps its rows and the tail is the one cut.
    $tail = array_slice($tail, 0, max(0, $rows - count($lines)));

    while (count($lines) < $rows - count($tail)) {
      $lines[] = '';
    }

    foreach ($tail as $line) {
      $lines[] = $line;
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
   * Run a region's blocks the way it was declared they run.
   *
   * An entered panel is where the next layout starts, which is where depth
   * comes from rather than a fifth level. Only the renderer knows the box it
   * has to fit into, so the recursion happens here.
   *
   * Going into a panel replaces what its region held with the panel's own
   * contents, so the rows placed beside it are the ones you left behind - the
   * sibling rows of the panel you came from. A screen's regions are the
   * exception, because what stands beside the panel there is the furniture the
   * session drew around the form rather than anything the form declared.
   * Coming back out draws every one of them again.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param int $rows
   *   The rows it was given.
   * @param int $columns
   *   The columns it was given.
   * @param bool $furnished
   *   Whether it holds the furniture a session puts around a form.
   *
   * @return list<string>
   *   The rows, before the region is sized to the space it has.
   */
  protected function arrange(Region $region, int $rows, int $columns, bool $furnished = FALSE): array {
    $entered = $this->entered($region);

    if ($entered instanceof Panel) {
      return $furnished ? $this->within($region, $entered, $rows, $columns) : $this->lay($entered->currentLayout(), $rows, $columns);
    }

    $drawn = array_values($this->rendered($region->headBlocks(), $region->isPreviewing()));

    if ($region->flowAxis() !== Axis::Columns) {
      return $this->down($drawn);
    }

    return $this->across($drawn, array_values($this->rendered($region->tailBlocks())), $columns);
  }

  /**
   * The panel a region was entered through, if it holds one.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   *
   * @return \DrevOps\Tui\Block\Panel|null
   *   The panel, or NULL when nothing in it has been gone into.
   */
  protected function entered(Region $region): ?Panel {
    foreach ($region->blocks() as $block) {
      if ($block instanceof Panel && $block->isEntered()) {
        return $block;
      }
    }

    return NULL;
  }

  /**
   * Whether the panel you are in is deeper than a given one.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return bool
   *   TRUE when a panel it holds has been gone into as well.
   */
  protected function deeper(Panel $panel): bool {
    foreach ($panel->blocks() as $block) {
      if ($block instanceof Panel && $block->isEntered()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Draw the panel a furnished region holds, and what stands beside it.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel it was entered through.
   * @param int $rows
   *   The rows it was given.
   * @param int $columns
   *   The columns it was given.
   *
   * @return list<string>
   *   The rows.
   */
  protected function within(Region $region, Panel $panel, int $rows, int $columns): array {
    // Going deeper replaces the view, so the furniture goes with the rows it
    // stood beside: what is in front of the reader is the panel they walked
    // into and nothing else.
    if ($this->deeper($panel)) {
      return $this->lay($panel->currentLayout(), $rows, $columns);
    }

    [$above, $below] = $this->beside($region, $panel);
    $drawn = $this->lay($panel->currentLayout(), $this->fit($panel, [...$above, ...$below], $rows), $columns);

    // The panel stacks as one more block, so what shows between it and what
    // stands beside it is the air that shows between any two of them.
    return $this->down([...$above, ...($drawn === [] ? [] : [implode("\n", $drawn)]), ...$below]);
  }

  /**
   * What a region draws before the panel it holds, and what it draws after it.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return array{list<string>,list<string>}
   *   What each of them drew, in the order they were placed.
   */
  protected function beside(Region $region, Panel $panel): array {
    $above = [];
    $below = [];
    $seen = FALSE;

    foreach ($region->headBlocks() as $block) {
      if ($block === $panel) {
        $seen = TRUE;

        continue;
      }

      if ($seen) {
        $below[] = $block;

        continue;
      }

      $above[] = $block;
    }

    return [array_values($this->rendered($above)), array_values($this->rendered($below))];
  }

  /**
   * The rows a panel takes of a region, once its neighbours have theirs.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   * @param list<string> $beside
   *   What the blocks drawn beside it drew.
   * @param int $rows
   *   The rows the region was given.
   *
   * @return int
   *   The rows.
   */
  protected function fit(Panel $panel, array $beside, int $rows): int {
    $spent = 0;

    foreach ($beside as $drawn) {
      $spent += substr_count($drawn, "\n") + 1;
    }

    // Every piece past the first is told apart from the one above it, and the
    // panel is one of them.
    $spent += $this->spaced() ? count($beside) : 0;

    return max(0, min($rows - $spent, $this->height($panel->currentLayout())));
  }

  /**
   * What a region draws at the far end of its flow, once it runs down it.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   *
   * @return list<string>
   *   The rows, none when it packs nothing from the end - or when it runs
   *   across, where what packs from the end shares the rows rather than
   *   following them.
   */
  protected function tailed(Region $region): array {
    if ($region->tailBlocks() === [] || $region->flowAxis() === Axis::Columns) {
      return [];
    }

    return $this->down(array_values($this->rendered($region->tailBlocks())));
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
   * @param bool $previews
   *   Whether a panel among them shows what is behind it rather than a row.
   *
   * @return array<int,string>
   *   What each block that drew anything drew.
   */
  protected function rendered(array $blocks, bool $previews = FALSE): array {
    $drawn = [];

    foreach ($blocks as $index => $block) {
      // A block the answers took off the screen is not there at all: it costs
      // no row, rather than costing a blank one.
      if ($block instanceof DependCapableInterface && $block->isHidden()) {
        continue;
      }

      $rendered = $previews && $block instanceof Panel ? $block->preview($this->theme) : $block->render($this->theme);

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
   * Run rendered blocks across a region, from each end of it.
   *
   * @param list<string> $head
   *   The blocks packed from the start of the axis.
   * @param list<string> $tail
   *   The blocks packed from the end of it.
   * @param int $columns
   *   The columns the region was given, which is what the far end is.
   *
   * @return list<string>
   *   The rows.
   */
  protected function across(array $head, array $tail, int $columns): array {
    $start = $this->run($head);

    if ($tail === []) {
      return $start;
    }

    $end = $this->run($tail);
    $height = max(count($start), count($end));
    $lines = [];

    for ($row = 0; $row < $height; $row++) {
      $lines[] = $this->met($start[$row] ?? '', $end[$row] ?? '', $columns);
    }

    return $lines;
  }

  /**
   * One row's two runs, the second sitting against the far edge.
   *
   * @param string $start
   *   What packs from the start of the axis.
   * @param string $end
   *   What packs from the end of it.
   * @param int $columns
   *   The columns the region was given.
   *
   * @return string
   *   The row: the two runs, with the second against the far edge and cut
   *   where it would run into the first.
   */
  protected function met(string $start, string $end, int $columns): string {
    // The start keeps every column it drew, so where the two meet in the
    // middle it is the end that loses what does not fit.
    $room = max(0, $columns - Ansi::width($start) - 1);
    $end = Box::fit($end, min($room, Ansi::width($end)));

    if ($end === '') {
      return rtrim($start);
    }

    return Box::fit($start, $columns - Ansi::width($end)) . $end;
  }

  /**
   * Run rendered blocks along one axis, each at the width it drew.
   *
   * @param list<string> $drawn
   *   The rendered blocks.
   *
   * @return list<string>
   *   The rows.
   */
  protected function run(array $drawn): array {
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
