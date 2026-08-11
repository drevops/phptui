<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\ScopedKeyMap;

/**
 * Sends a key to the innermost thing that binds it.
 *
 * Keys travel inward and drawing travels outward. A key goes to the focused
 * block if that block binds it, and only outward from there to the panel it
 * sits in.
 *
 * That one rule accounts for what would otherwise read as a special case: an
 * open text field binds every printable key, because each is something you are
 * typing, so the help key reaches it and becomes a character. Close the field
 * and it binds none, so the same key travels outward and opens help. The same
 * rule is why a nested panel does not swallow the keys that move the cursor
 * past it: until you have gone into it, it is a row rather than somewhere you
 * are.
 *
 * @package DrevOps\Tui\Screen
 */
final class KeyRouter {

  /**
   * Which of the focusable blocks has the cursor.
   */
  protected int $cursor = 0;

  /**
   * The field whose help is showing, if any is.
   */
  protected ?Field $help = NULL;

  /**
   * The panel the whole form hangs from, whichever one you are in.
   */
  protected Panel $root;

  /**
   * The panels left behind on the way in, with the row each was left on.
   *
   * @var list<array{panel:\DrevOps\Tui\Block\Panel,cursor:int}>
   */
  protected array $trail = [];

  /**
   * The blocks a session draws beside a panel, keyed by the panel.
   *
   * @var array<int,list<\DrevOps\Tui\Block\Capability\FocusCapableInterface>>
   */
  protected array $furniture = [];

  /**
   * Construct a key router.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel it moves around, which is the panel you are in.
   */
  public function __construct(protected Panel $panel) {
    $this->root = $panel;

    // One declaration outlives the session driving it, so a session opens on a
    // form nobody has walked into rather than wherever the last one stopped.
    foreach (Tree::panels($this->panel) as $panel) {
      $panel->leave();
    }

    $this->panel->enter();
    $this->settle();
  }

  /**
   * Make every block in the panel answer to one set of bindings.
   *
   * @param \DrevOps\Tui\Input\KeyMap $keys
   *   The resolved bindings for the whole form.
   *
   * @return $this
   *   The router.
   */
  public function bind(KeyMap $keys): self {
    $this->rebind($keys, $this->panel);

    return $this;
  }

  /**
   * Say what a session draws beside a panel.
   *
   * The buttons that end a form are not among the rows it declares - a session
   * draws them around it - so the cursor could not reach them by walking the
   * panel alone. It reaches them last, after everything the panel holds,
   * because that is where they are drawn.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel they stand beside.
   * @param \DrevOps\Tui\Block\Capability\FocusCapableInterface ...$blocks
   *   The blocks.
   *
   * @return $this
   *   The router.
   */
  public function furnish(Panel $panel, FocusCapableInterface ...$blocks): self {
    $this->furniture[spl_object_id($panel)] = array_values($blocks);
    $this->settle();

    return $this;
  }

  /**
   * The panel you are in.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel, which is whichever one was gone into last.
   */
  public function current(): Panel {
    return $this->panel;
  }

  /**
   * The titles of the panels entered to get here, outermost first.
   *
   * @return list<string>
   *   The trail.
   */
  public function trail(): array {
    $titles = array_map(static fn(array $step): string => $step['panel']->title(), $this->trail);
    $titles[] = $this->panel->title();

    return $titles;
  }

  /**
   * The block the cursor is on.
   *
   * @return \DrevOps\Tui\Block\Capability\FocusCapableInterface|null
   *   The block, or NULL when nothing in the panel takes focus.
   */
  public function focused(): ?FocusCapableInterface {
    return $this->focusable()[$this->cursor] ?? NULL;
  }

  /**
   * The innermost thing a key reaches right now.
   *
   * @return \DrevOps\Tui\Block\Capability\BindCapableInterface
   *   The open block under the cursor, else the panel around it.
   */
  public function binder(): BindCapableInterface {
    $focused = $this->focused();

    return $focused instanceof Field && $focused->mode() === Mode::Edit ? $focused : $this->panel;
  }

  /**
   * The keys that apply right now.
   *
   * @return \DrevOps\Tui\Input\ScopedKeyMap
   *   The innermost binder's bindings.
   */
  public function bindings(): ScopedKeyMap {
    return $this->binder()->bindings();
  }

  /**
   * What those keys do, as labelled fragments.
   *
   * @return list<\DrevOps\Tui\Input\Hint>
   *   The fragments.
   */
  public function hints(): array {
    return $this->binder()->hints();
  }

  /**
   * Rewrite a legend from the keys that apply right now.
   *
   * @param \DrevOps\Tui\Block\Legend $legend
   *   The legend.
   *
   * @return \DrevOps\Tui\Block\Legend
   *   The same legend, now advertising the innermost binder's keys.
   */
  public function refresh(Legend $legend): Legend {
    return $legend->advertise($this->bindings(), ...$this->hints());
  }

  /**
   * Whether a field's help is showing.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isShowingHelp(): bool {
    return $this->help instanceof Field;
  }

  /**
   * The field whose help is showing.
   *
   * @return \DrevOps\Tui\Block\Field|null
   *   The field, or NULL when none is showing.
   */
  public function helping(): ?Field {
    return $this->help;
  }

  /**
   * Come back out of the panel you are in, as the key that does would.
   *
   * @return bool
   *   TRUE when there was somewhere to come back to.
   */
  public function leave(): bool {
    return $this->ascend();
  }

  /**
   * Put the cursor back on a row that is there, after some stopped being.
   *
   * The answers decide which rows are there at all, so a row can leave from
   * under the cursor - and a cursor counting to a row that is gone is on
   * nothing at all.
   */
  public function reframe(): void {
    $this->cursor = max(0, min($this->cursor, max(0, count($this->focusable()) - 1)));
    $this->settle();
  }

  /**
   * Come back out of every section the answers have taken off the form.
   *
   * A section is somewhere you are rather than something you are looking at, so
   * one that stops being there while you are inside it cannot simply stop being
   * drawn: there would be nowhere left to stand. The way out is the way you
   * came in, as far out as it takes to reach a section that is still there -
   * and never past the outermost one, which is not somewhere you came from.
   */
  public function resurface(): void {
    while ($this->panel->isHidden() && $this->trail !== []) {
      $this->ascend();
    }
  }

  /**
   * Send a key where it belongs.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  public function handle(Key $key): void {
    if ($this->help instanceof Field) {
      // Any key dismisses help, so a reader never has to find the way out.
      $this->help = NULL;

      return;
    }

    $focused = $this->focused();

    if ($focused instanceof Field && $focused->binds($key)) {
      $focused->capture($key);

      return;
    }

    if ($this->panel->binds($key)) {
      $this->inPanel($focused, $key);
    }

    // A key neither of them binds has reached the screen, which claims none of
    // its own.
  }

  /**
   * Handle a key that travelled outward to the panel.
   *
   * @param \DrevOps\Tui\Block\Capability\FocusCapableInterface|null $focused
   *   The block with the cursor, if any has it.
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  protected function inPanel(?FocusCapableInterface $focused, Key $key): void {
    $bindings = $this->panel->bindings();

    if ($bindings->matches($key, Action::MoveDown)) {
      $this->moveBy(1);

      return;
    }

    if ($bindings->matches($key, Action::MoveUp)) {
      $this->moveBy(-1);

      return;
    }

    if ($bindings->matches($key, Action::MoveRight)) {
      $this->moveAcross(1);

      return;
    }

    if ($bindings->matches($key, Action::MoveLeft)) {
      $this->moveAcross(-1);

      return;
    }

    if ($bindings->matches($key, Action::Activate)) {
      $this->activate($focused);

      return;
    }

    if ($bindings->matches($key, Action::Back)) {
      $this->ascend();

      return;
    }

    // Help is only offered where it leads somewhere, so a field carrying none
    // leaves the key doing nothing rather than opening a blank page.
    if ($bindings->matches($key, Action::Help) && $focused instanceof Field && $focused->helpText() !== '') {
      $this->help = $focused;
    }
  }

  /**
   * Do what selecting the block under the cursor does.
   *
   * @param \DrevOps\Tui\Block\Capability\FocusCapableInterface|null $focused
   *   The block with the cursor, if any has it.
   */
  protected function activate(?FocusCapableInterface $focused): void {
    if ($focused instanceof Field) {
      $focused->open();

      return;
    }

    if ($focused instanceof Panel) {
      $this->descend($focused);
    }
  }

  /**
   * Go into a nested panel, so the screen becomes its contents.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to go into.
   */
  protected function descend(Panel $panel): void {
    $panel->prepare();

    $this->trail[] = ['panel' => $this->panel, 'cursor' => $this->cursor];
    $this->panel = $panel->enter();
    $this->cursor = 0;
    $this->settle();
  }

  /**
   * Come back out of a panel, restoring the screen and the row it was left on.
   *
   * @return bool
   *   TRUE when there was somewhere to come back to.
   */
  protected function ascend(): bool {
    // The outermost panel is not somewhere you came from, so there is nowhere
    // to go back to and the key does nothing.
    if ($this->trail === []) {
      return FALSE;
    }

    $step = array_pop($this->trail);

    $this->panel->leave();
    $this->panel = $step['panel'];
    $this->cursor = $step['cursor'];
    $this->settle();

    return TRUE;
  }

  /**
   * Move the cursor, stopping at the ends rather than wrapping.
   *
   * @param int $delta
   *   The rows to move by.
   */
  protected function moveBy(int $delta): void {
    $count = count($this->focusable());

    if ($count === 0) {
      return;
    }

    $this->cursor = $this->stepped($delta) ?? max(0, min($this->cursor + $delta, $count - 1));
    $this->settle();
  }

  /**
   * Move the cursor along the visual row of windows it is on.
   *
   * Windows sit beside each other, so what is next to one is a neighbour rather
   * than the next row: moving across walks the row and stops at its ends, and
   * anywhere else there is nothing beside the cursor to move to.
   *
   * @param int $delta
   *   The windows to move by.
   */
  protected function moveAcross(int $delta): void {
    $columns = $this->columnEntries();

    // An arrangement running across draws its regions side by side, so the
    // cursor steps between them - onto the first row the next region holds.
    // Walking the rows within one region stays the job of up and down.
    if ($columns !== []) {
      $this->stepAcross($columns, $delta);

      return;
    }

    $at = $this->windowAt();

    if ($at === NULL) {
      return;
    }

    [$row, $column] = $at;
    $windows = $this->windowRows();
    $next = $column + $delta;

    if ($next < 0 || $next >= count($windows[$row])) {
      return;
    }

    $this->cursor = $windows[$row][$next];
    $this->settle();
  }

  /**
   * The row each region of an arrangement running across is entered at.
   *
   * @return list<int>
   *   The first focusable row of each region, in the order they are drawn;
   *   empty when the panel is arranged down rather than across, or when fewer
   *   than two of its regions hold a row the cursor can land on.
   */
  protected function columnEntries(): array {
    $layout = $this->panel->currentLayout();

    if ($layout->axis() !== Axis::Columns) {
      return [];
    }

    $entries = [];
    $index = 0;

    foreach ($this->held() as $blocks) {
      if ($blocks !== []) {
        $entries[] = $index;
      }

      $index += count($blocks);
    }

    return count($entries) > 1 ? $entries : [];
  }

  /**
   * Step the cursor to the region beside the one it is in.
   *
   * @param list<int> $entries
   *   The row each region is entered at.
   * @param int $delta
   *   The regions to move by.
   */
  protected function stepAcross(array $entries, int $delta): void {
    $in = 0;

    // The cursor is in the last region it has passed the entry row of, so a
    // region is stepped out of from any of its rows rather than its first.
    foreach ($entries as $at => $entry) {
      if ($this->cursor >= $entry) {
        $in = $at;
      }
    }

    $next = $in + $delta;

    if ($next < 0 || $next >= count($entries)) {
      return;
    }

    $this->cursor = $entries[$next];
    $this->settle();
  }

  /**
   * Where the cursor lands when it steps off a row of windows, if it is on one.
   *
   * @param int $delta
   *   The visual rows to move by.
   *
   * @return int|null
   *   The block to land on, or NULL when the cursor is not on a window and the
   *   move is the ordinary one from a row to the row after it.
   */
  protected function stepped(int $delta): ?int {
    $at = $this->windowAt();

    if ($at === NULL) {
      return NULL;
    }

    [$row, $column] = $at;
    $windows = $this->windowRows();
    $next = $row + $delta;

    // Off the top or the bottom of the grid is out of it: the whole grid is one
    // step, so what is above or below it is the row beside the grid rather than
    // the window the cursor happened to be under. Where there is nothing beyond
    // it the move stops, because the ordinary step to the next row would walk
    // the windows sideways - which is what moving across is for.
    if (!isset($windows[$next])) {
      return $this->beside($windows, $delta) ?? $this->cursor;
    }

    // A shorter row cannot be entered past its end, so the cursor lands on the
    // window nearest the column it came from.
    return $windows[$next][min($column, count($windows[$next]) - 1)];
  }

  /**
   * The block on the far side of the grid, in the direction of a move.
   *
   * @param list<list<int>> $windows
   *   The blocks of each visual row of windows.
   * @param int $delta
   *   The direction: below the grid when positive, above it when negative.
   *
   * @return int|null
   *   The block to land on, or NULL when there is nothing beyond the grid.
   */
  protected function beside(array $windows, int $delta): ?int {
    $placed = $windows === [] ? [] : array_merge(...$windows);

    if ($placed === []) {
      return NULL;
    }

    $count = count($this->focusable());

    // Whatever the next thing that takes the cursor is: the rows the panel
    // wrote either side of its windows, then whatever a session drew beside the
    // panel. What kind of block it is never comes into it - only that it is the
    // next one the cursor can be on once every window is behind it.
    for ($index = ($delta > 0 ? max($placed) : min($placed)) + $delta; $index >= 0 && $index < $count; $index += $delta) {
      if (!in_array($index, $placed, TRUE)) {
        return $index;
      }
    }

    return NULL;
  }

  /**
   * Which window of the grid the cursor is on, if it is on one.
   *
   * @return array{int,int}|null
   *   The visual row and the place in it, or NULL when the cursor is not on a
   *   window.
   */
  protected function windowAt(): ?array {
    foreach ($this->windowRows() as $row => $blocks) {
      $column = array_search($this->cursor, $blocks, TRUE);

      if (is_int($column)) {
        return [$row, $column];
      }
    }

    return NULL;
  }

  /**
   * The blocks of each visual row of windows, in the order they are drawn.
   *
   * @return list<list<int>>
   *   One entry per visual row, naming the focusable blocks the windows of that
   *   row are; empty when the panel is arranged by no grid.
   */
  protected function windowRows(): array {
    // Read off the arrangement rather than off the panel, because how what a
    // panel holds sits on screen is the layout's to say and the panel nests one
    // precisely so it never has to know. A window is a region there, so which
    // of them sit beside each other is the shape of its lines.
    $layout = $this->panel->currentLayout();
    $numbered = [];
    $index = 0;

    foreach ($this->held() as $name => $blocks) {
      $numbered[$name] = [];

      foreach ($blocks as $block) {
        $numbered[$name][] = $index;
        $index++;
      }
    }

    $rows = [];

    foreach ($layout->lines() as $line) {
      $row = [];

      foreach ($line as $name) {
        // A region drawing rows is walked a step at a time like any other, so
        // only the ones showing what is behind them are windows.
        if (!$layout->in($name)->isPreviewing()) {
          continue;
        }

        foreach ($numbered[$name] ?? [] as $place) {
          $row[] = $place;
        }
      }

      if ($row !== []) {
        $rows[] = $row;
      }
    }

    return $rows;
  }

  /**
   * Put the cursor on the block it is counting to, and take it off the rest.
   *
   * The rest is the whole form rather than the panel you are in. A panel you
   * walked out of goes on being drawn - as the row that leads back into it, or
   * as a window previewing what is behind it - so a row left holding the cursor
   * in there would go on drawing one, and a reader would see two.
   */
  protected function settle(): void {
    $on = $this->focused();

    foreach ($this->everywhere() as $block) {
      if ($block === $on) {
        $block->focus();

        continue;
      }

      $block->blur();
    }
  }

  /**
   * Every block on the form the cursor can be on, wherever that is.
   *
   * @return list<\DrevOps\Tui\Block\Capability\FocusCapableInterface>
   *   The blocks: everything every panel of the form holds, and everything a
   *   session draws beside one of them.
   */
  protected function everywhere(): array {
    $blocks = [];

    foreach (Tree::panels($this->root) as $panel) {
      foreach ($panel->blocks() as $block) {
        if ($block instanceof FocusCapableInterface) {
          $blocks[] = $block;
        }
      }
    }

    // The furniture is drawn beside the form rather than declared in it, so no
    // walk of the tree reaches it.
    foreach ($this->furniture as $beside) {
      foreach ($beside as $block) {
        $blocks[] = $block;
      }
    }

    return $blocks;
  }

  /**
   * Make a panel and everything in it answer to one set of bindings.
   *
   * @param \DrevOps\Tui\Input\KeyMap $keys
   *   The resolved bindings.
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to spread them through.
   */
  protected function rebind(KeyMap $keys, Panel $panel): void {
    $panel->bind($keys);

    foreach ($panel->currentLayout()->names() as $name) {
      foreach ($panel->currentLayout()->in($name)->blocks() as $block) {
        if ($block instanceof Panel) {
          $this->rebind($keys, $block);

          continue;
        }

        if ($block instanceof BindCapableInterface) {
          $block->bind($keys);
        }
      }
    }
  }

  /**
   * The blocks the cursor can land on, in the order they are drawn.
   *
   * @return list<\DrevOps\Tui\Block\Capability\FocusCapableInterface>
   *   The blocks: everything the panel you are in holds, then whatever the
   *   session draws beside it.
   */
  protected function focusable(): array {
    $blocks = [];

    foreach ($this->held() as $held) {
      foreach ($held as $block) {
        $blocks[] = $block;
      }
    }

    return [...$blocks, ...($this->furniture[spl_object_id($this->panel)] ?? [])];
  }

  /**
   * The blocks the cursor can land on, region by region.
   *
   * @return array<string,list<\DrevOps\Tui\Block\Capability\FocusCapableInterface>>
   *   The blocks, keyed by the region they are drawn in, in declaration order.
   */
  protected function held(): array {
    $layout = $this->panel->currentLayout();
    $held = [];

    foreach ($layout->names() as $name) {
      $held[$name] = [];

      foreach ($layout->in($name)->blocks() as $block) {
        // A row the answers took off the screen is not somewhere the cursor can
        // be, because it is not anywhere.
        if ($block instanceof DependCapableInterface && $block->isHidden()) {
          continue;
        }

        // A block that does not claim the cursor is skipped rather than landed
        // on, which is what lets markup sit between two fields.
        if ($block instanceof FocusCapableInterface) {
          $held[$name][] = $block;
        }
      }
    }

    return $held;
  }

}
