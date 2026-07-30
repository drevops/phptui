<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;

/**
 * Sends a key to the innermost thing that binds it.
 *
 * Keys travel inward and drawing travels outward. A key goes to the focused
 * block if that block binds it, and only outward from there to the panel it
 * sits in.
 *
 * That one rule accounts for what would otherwise read as a special case: an
 * open field binds every printable key, because each is something you are
 * typing, so the help key reaches it and becomes a character. Close the field
 * and it binds none, so the same key travels outward and opens help.
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
   * Construct a key router.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel it moves around.
   */
  public function __construct(
    protected Panel $panel,
  ) {
  }

  /**
   * The block the cursor is on.
   *
   * @return \DrevOps\Tui\Block\Field|null
   *   The block, or NULL when nothing in the panel takes focus.
   */
  public function focused(): ?Field {
    return $this->focusable()[$this->cursor] ?? NULL;
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

    if ($focused instanceof Field && $focused->mode() === Mode::Edit) {
      $this->inEdit($focused, $key);

      return;
    }

    $this->inPanel($focused, $key);
  }

  /**
   * Handle a key an open field binds.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The open field.
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  protected function inEdit(Field $field, Key $key): void {
    if ($key->name === KeyName::Enter) {
      $field->accept();

      return;
    }

    if ($key->name === KeyName::Escape) {
      $field->close();
    }

    // Everything else is something being typed, and stops here. Which is why a
    // closed field's help key is free and an open one's is not.
  }

  /**
   * Handle a key that reached the panel.
   *
   * @param \DrevOps\Tui\Block\Field|null $focused
   *   The block with the cursor, if any has it.
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  protected function inPanel(?Field $focused, Key $key): void {
    if ($key->name === KeyName::Down) {
      $this->cursor = min($this->cursor + 1, max(0, count($this->focusable()) - 1));

      return;
    }

    if ($key->name === KeyName::Up) {
      $this->cursor = max(0, $this->cursor - 1);

      return;
    }

    if ($key->name === KeyName::Enter && $focused instanceof Field) {
      $focused->open();

      return;
    }

    // Help is only offered where it leads somewhere, so a field carrying none
    // leaves the key doing nothing rather than opening a blank page.
    if ($key->char === '?' && $focused instanceof Field && $focused->helpText() !== '') {
      $this->help = $focused;
    }
  }

  /**
   * The blocks the cursor can land on, in the order they are drawn.
   *
   * @return list<\DrevOps\Tui\Block\Field>
   *   The blocks.
   */
  protected function focusable(): array {
    $blocks = [];

    foreach ($this->panel->currentLayout()->names() as $name) {
      foreach ($this->panel->currentLayout()->in($name)->blocks() as $block) {
        if ($block instanceof Field) {
          $blocks[] = $block;
        }
      }
    }

    return $blocks;
  }

}
