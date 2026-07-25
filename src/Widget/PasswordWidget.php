<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\RevealCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableInterface;
use DrevOps\Tui\Widget\Capability\TextEditCapableTrait;

/**
 * Single-line text input rendered masked; the accepted value stays plain.
 *
 * Two opt-in enhancements, both off by default: a reveal toggle that cycles the
 * live display between hidden, masked and plaintext without touching the stored
 * value; and a confirmation mode that prompts for the value a second time and
 * rejects a mismatch before accepting.
 *
 * @package DrevOps\Tui\Widget
 */
class PasswordWidget extends AbstractWidget implements TextEditCapableInterface, RevealCapableInterface {

  use TextEditCapableTrait;

  /**
   * The current live display mode.
   */
  protected PasswordDisplay $display = PasswordDisplay::Masked;

  /**
   * The stashed first entry while confirming; NULL before or without confirm.
   */
  protected ?string $firstEntry = NULL;

  /**
   * Construct a password widget.
   *
   * @param string $buffer
   *   The initial value (and live input buffer).
   * @param bool $revealable
   *   Whether the reveal toggle is enabled.
   * @param bool $confirm
   *   Whether confirmation mode is enabled.
   */
  public function __construct(string $buffer = '', protected bool $revealable = FALSE, protected bool $confirm = FALSE) {
    $this->initTextBuffer($buffer);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::Password);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->revealable && $keys->matches($key, Action::Reveal)) {
      $this->toggleReveal();

      return;
    }

    if ($this->confirm && $keys->matches($key, Action::Accept)) {
      $this->submit();

      return;
    }

    if ($this->handleCancel($key)) {
      return;
    }

    if ($this->handleAccept($key)) {
      return;
    }

    $this->handleTextEditKey($key);
  }

  /**
   * {@inheritdoc}
   *
   * Cycles the live display between hidden, masked and plaintext; inert unless
   * the reveal toggle is enabled. The stored value is never affected.
   */
  public function toggleReveal(): void {
    if (!$this->revealable) {
      return;
    }

    $this->display = $this->display->next();
  }

  /**
   * Advance the two-step confirmation on Enter.
   */
  protected function submit(): void {
    if ($this->firstEntry === NULL) {
      $this->firstEntry = $this->buffer;
      $this->buffer = '';
      $this->cursor = 0;
      $this->error = NULL;

      return;
    }

    if ($this->buffer !== $this->firstEntry) {
      $this->error = Translator::t('Passwords do not match.');
      $this->reset();

      return;
    }

    $this->accept($this->firstEntry);

    // A validator may still reject the matched value; restart on failure so the
    // shown error is not stranded against a completed widget.
    if (!$this->isComplete()) {
      $this->reset();
    }
  }

  /**
   * Clear both entries and return to the first prompt, keeping any error.
   */
  protected function reset(): void {
    $this->firstEntry = NULL;
    $this->buffer = '';
    $this->cursor = 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $rows = [$this->renderLine($theme)];

    if ($this->firstEntry !== NULL) {
      $rows[] = $theme->footer(Translator::t('re-enter to confirm'));
    }

    return implode("\n", $rows);
  }

  /**
   * {@inheritdoc}
   *
   * The reveal toggle is the non-obvious action, so it leads when the widget
   * is revealable; otherwise the base accept/cancel hints stand alone.
   */
  #[\Override]
  public function hints(): array {
    if (!$this->revealable) {
      return parent::hints();
    }

    return [new Hint('reveal', Action::Reveal), ...parent::hints()];
  }

  /**
   * Render the input line for the current display mode.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the mask and caret glyphs.
   *
   * @return string
   *   The rendered input line.
   */
  protected function renderLine(ThemeInterface $theme): string {
    return match ($this->display) {
      PasswordDisplay::Hidden => $theme->renderInput('', ''),
      PasswordDisplay::Masked => $theme->renderInput(str_repeat($theme->mask(), $this->cursor), str_repeat($theme->mask(), Strings::length($this->buffer) - $this->cursor)),
      PasswordDisplay::Plaintext => $this->renderInputLine($theme),
    };
  }

}
