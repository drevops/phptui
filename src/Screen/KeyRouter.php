<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\Panel;
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
   * The panels left behind on the way in, with the row each was left on.
   *
   * @var list<array{panel:\DrevOps\Tui\Block\Panel,cursor:int}>
   */
  protected array $trail = [];

  /**
   * Construct a key router.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel it moves around, which is the panel you are in.
   */
  public function __construct(
    protected Panel $panel,
  ) {
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
   */
  protected function ascend(): void {
    // The outermost panel is not somewhere you came from, so there is nowhere
    // to go back to and the key does nothing.
    if ($this->trail === []) {
      return;
    }

    $step = array_pop($this->trail);

    $this->panel->leave();
    $this->panel = $step['panel'];
    $this->cursor = $step['cursor'];
    $this->settle();
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

    $this->cursor = max(0, min($this->cursor + $delta, $count - 1));
    $this->settle();
  }

  /**
   * Put the cursor on the block it is counting to, and take it off the rest.
   */
  protected function settle(): void {
    foreach ($this->focusable() as $index => $block) {
      if ($index === $this->cursor) {
        $block->focus();

        continue;
      }

      $block->blur();
    }
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
   *   The blocks.
   */
  protected function focusable(): array {
    $blocks = [];

    foreach ($this->panel->currentLayout()->names() as $name) {
      foreach ($this->panel->currentLayout()->in($name)->blocks() as $block) {
        // A block that does not claim the cursor is skipped rather than landed
        // on, which is what lets markup sit between two fields.
        if ($block instanceof FocusCapableInterface) {
          $blocks[] = $block;
        }
      }
    }

    return $blocks;
  }

}
