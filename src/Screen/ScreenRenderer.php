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
   * The columns a frame spends on its own border and gutter, both sides.
   */
  public const int CHROME = 4;

  /**
   * The columns left clear between two things drawn side by side.
   *
   * The air between them, in the family of the blank row that shows between
   * one block and the next: a layout takes it off the width before dividing
   * what is left, and leaving it is what drawing them does.
   */
  protected const int GUTTER = 2;

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
   *   The layout the region belongs to, which is what says how it deals what
   *   it holds.
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

    foreach ($this->pieces($layout, $layout->in($name)) as $piece) {
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
   * A grid stacks as one piece however many windows it deals into it, which is
   * exactly how it draws: windows sitting beside each other share the rows they
   * are drawn on, so counting them one under another would say the region is
   * several times as deep as anyone can see.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout the region belongs to.
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   *
   * @return list<array{height:int,blocks:list<\DrevOps\Tui\Block\BlockInterface>,offsets:list<int>}>
   *   Each piece: the rows it comes to, the blocks drawn in it, and the row
   *   each of those starts on within it.
   */
  protected function pieces(LayoutInterface $layout, Region $region): array {
    $shape = $layout->deal();
    $tail = $this->stacked($region->tailBlocks());

    if ($shape === []) {
      return [...$this->stacked($region->headBlocks()), ...$tail];
    }

    [$panels, $above, $below] = $this->split($region->headBlocks());
    $windows = $this->windowPiece($panels, $shape);

    return [
      ...$this->stacked($above),
      ...($windows['height'] > 0 ? [$windows] : []),
      ...$this->stacked($below),
      ...$tail,
    ];
  }

  /**
   * One piece per block, in the order they are drawn.
   *
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   *
   * @return list<array{height:int,blocks:list<\DrevOps\Tui\Block\BlockInterface>,offsets:list<int>}>
   *   The pieces.
   */
  protected function stacked(array $blocks): array {
    $pieces = [];

    foreach ($blocks as $block) {
      // The panel you are in draws its own layout in place of its row, so what
      // it comes to is what that layout comes to.
      if ($block instanceof Panel && $block->isEntered()) {
        $pieces[] = ['height' => $this->height($block->currentLayout()), 'blocks' => [$block], 'offsets' => [0]];

        continue;
      }

      $rendered = $this->rendered([$block])[0] ?? NULL;

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
   * The rows a layout's regions come to when nothing has sized them.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   *
   * @return int
   *   The rows: what its regions come to together down the axis, and what the
   *   deepest of them comes to across it, where they share the rows instead.
   */
  protected function height(LayoutInterface $layout): int {
    $rows = 0;

    foreach ($layout->names() as $name) {
      $region = $layout->in($name);
      // A region declared a size takes it whatever it holds, which is the whole
      // of what fixing a size means.
      $held = $region->fixedSize() ?? $this->extent($layout, $name)[0];
      $rows = $layout->axis() === Axis::Rows ? $rows + $held : max($rows, $held);
    }

    return $rows;
  }

  /**
   * The one piece a grid of windows stacks as.
   *
   * @param list<\DrevOps\Tui\Block\Panel> $panels
   *   The panels, in the order they were placed.
   * @param list<int> $grid
   *   How many of them share each visual row.
   *
   * @return array{height:int,blocks:list<\DrevOps\Tui\Block\BlockInterface>,offsets:list<int>}
   *   The rows the grid comes to, its windows, and the row each starts on.
   */
  protected function windowPiece(array $panels, array $grid): array {
    $blocks = [];
    $offsets = [];
    $height = 0;
    $taken = 0;

    foreach ($grid as $count) {
      // Every visual row after the first is told apart from the one above it,
      // exactly as it is drawn.
      if ($height > 0) {
        $height++;
      }

      $tallest = 0;

      foreach (array_slice($panels, $taken, $count) as $panel) {
        $blocks[] = $panel;
        $offsets[] = $height;
        $tallest = max($tallest, substr_count($panel->preview($this->theme), "\n") + 1);
        $taken += 1;
      }

      $height += $tallest;
    }

    return ['height' => $height, 'blocks' => $blocks, 'offsets' => $offsets];
  }

  /**
   * Tell a region's windows apart from the rows around them.
   *
   * A window is a way into a panel, so which blocks a grid deals is the same
   * question the recursion already asks - and the only one left here, now that
   * how wide they are is the layout's.
   *
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   *
   * @return array{list<\DrevOps\Tui\Block\Panel>,list<\DrevOps\Tui\Block\BlockInterface>,list<\DrevOps\Tui\Block\BlockInterface>}
   *   The panels the grid deals, the rows above it, and the rows below it. A
   *   row placed before the first window stays above the grid and one placed
   *   after the last stays below it, so nothing moves past a row it was
   *   written above.
   */
  protected function split(array $blocks): array {
    $panels = [];
    $above = [];
    $below = [];
    $seen = FALSE;

    foreach ($blocks as $block) {
      if ($block instanceof Panel) {
        // A window is how a section draws where its siblings sit beside it, so
        // one the answers took off the form is dealt none and the row it was
        // in closes up. Where the rows around it go is decided by where it was
        // written rather than by whether it is there.
        $seen = TRUE;

        if (!$block->isHidden()) {
          $panels[] = $block;
        }

        continue;
      }

      if (!$seen) {
        $above[] = $block;

        continue;
      }

      $below[] = $block;
    }

    return [$panels, $above, $below];
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
        foreach ($this->fill($layout, $layout->in($name), $size, $columns, $furnished) as $line) {
          $out[] = $line;
        }
      }

      return $out;
    }

    $columns_out = [];

    foreach ($sizes as $name => $size) {
      $columns_out[] = $this->fill($layout, $layout->in($name), $rows, $size, $furnished);
    }

    return $this->paste($columns_out, $sizes, $rows);
  }

  /**
   * Draw a region's blocks into the space it was given.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout the region belongs to.
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
  protected function fill(LayoutInterface $layout, Region $region, int $rows, int $columns, bool $furnished = FALSE): array {
    $lines = $this->arrange($layout, $region, $rows, $columns, $furnished);
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
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout the region belongs to.
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
  protected function arrange(LayoutInterface $layout, Region $region, int $rows, int $columns, bool $furnished = FALSE): array {
    $entered = $this->entered($region);

    if ($entered instanceof Panel) {
      return $furnished ? $this->within($region, $entered, $rows, $columns) : $this->lay($entered->currentLayout(), $rows, $columns);
    }

    $head = $region->headBlocks();

    if ($layout->deal() !== []) {
      return $this->dealt($layout, $head, $columns);
    }

    $drawn = array_values($this->rendered($head));

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
   * Stack a region's own rows, then deal its windows into visual rows.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout, which is what says how many windows share a visual row and
   *   how much of the region each of them takes.
   * @param list<\DrevOps\Tui\Block\BlockInterface> $blocks
   *   The blocks.
   * @param int $columns
   *   The columns the region was given.
   *
   * @return list<string>
   *   The rows.
   */
  protected function dealt(LayoutInterface $layout, array $blocks, int $columns): array {
    [$panels, $above, $below] = $this->split($blocks);
    $windows = implode("\n", $this->windows($layout, $panels, $columns));

    // The whole grid stacks as one more block, so what shows between it and
    // the rows around it is the same air that shows between any two of them.
    return $this->down([
      ...array_values($this->rendered($above)),
      ...($windows === '' ? [] : [$windows]),
      ...array_values($this->rendered($below)),
    ]);
  }

  /**
   * Paste each visual row's windows side by side at the width they were given.
   *
   * @param \DrevOps\Tui\Screen\Layout\LayoutInterface $layout
   *   The layout.
   * @param list<\DrevOps\Tui\Block\Panel> $panels
   *   The panels, in the order they were placed.
   * @param int $columns
   *   The columns the region was given.
   *
   * @return list<string>
   *   The rows.
   */
  protected function windows(LayoutInterface $layout, array $panels, int $columns): array {
    $lines = [];
    $taken = 0;

    foreach ($layout->deal() as $count) {
      // Every visual row after the first is told apart from the one above it,
      // whatever the theme says about the air between one block and the next.
      if ($lines !== []) {
        $lines[] = '';
      }

      $width = $layout->share($columns, $count);
      $windows = [];
      $height = 0;

      foreach (array_slice($panels, $taken, $count) as $panel) {
        $window = explode("\n", $panel->preview($this->theme));
        $height = max($height, count($window));
        $windows[] = $window;
        $taken += 1;
      }

      for ($row = 0; $row < $height; $row++) {
        $cells = [];

        foreach ($windows as $window) {
          $cells[] = Box::fit($window[$row] ?? '', $width);
        }

        // The gutters can outgrow a tiny frame even at one-column cells, so the
        // assembled row is clamped to the region as a whole.
        $lines[] = rtrim(Box::fit(implode(str_repeat(' ', self::GUTTER), $cells), $columns));
      }
    }

    return $lines;
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
