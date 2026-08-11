<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\ActivateCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Capability\RejectCapableInterface;
use DrevOps\Tui\Block\Element\ActionsElementsInterface;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * The buttons that end the form.
 *
 * Submit, cancel, and any the form declares. Pressing one does something rather
 * than reveals something, which is what it activates for. It is the only block
 * other than a field that refuses anything - the submit is withheld while a
 * required field is empty - and unlike a field it holds no value while doing
 * so. Withholding it is drawn here too: the reason a form cannot be ended yet
 * is what the buttons say about themselves.
 *
 * @package DrevOps\Tui\Block
 */
final class Actions extends AbstractBlock implements ActivateCapableInterface, FocusCapableInterface, RejectCapableInterface {

  use FocusCapableTrait;

  /**
   * The buttons, keyed by name, in declaration order.
   *
   * @var array<string,string>
   */
  protected array $buttons = [];

  /**
   * The name of the button the cursor rests on, if any does.
   */
  protected ?string $selected = NULL;

  /**
   * The name of the button that was pressed, if one was.
   */
  protected ?string $activated = NULL;

  /**
   * The reason the form cannot be ended yet, if there is one.
   */
  protected ?string $refusal = NULL;

  /**
   * Declare a button.
   *
   * @param string $name
   *   The name it is addressed by.
   * @param string $label
   *   The label it draws.
   *
   * @return static
   *   The block.
   */
  public function action(string $name, string $label): static {
    $this->buttons[$name] = Ansi::sanitize($label);
    $this->selected ??= $name;

    return $this;
  }

  /**
   * The names of the buttons, in declaration order.
   *
   * @return list<string>
   *   The names.
   */
  public function names(): array {
    return array_keys($this->buttons);
  }

  /**
   * Rest the cursor on a button.
   *
   * @param string $name
   *   The button name.
   *
   * @return static
   *   The block.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the action name is unknown.
   */
  public function select(string $name): static {
    if (!isset($this->buttons[$name])) {
      throw new FormException(sprintf('Unknown action "%s". This block declares: %s.', $name, implode(', ', $this->names())));
    }

    $this->selected = $name;

    return $this;
  }

  /**
   * The button the cursor rests on.
   *
   * @return string|null
   *   The name, or NULL while no button is declared.
   */
  public function selected(): ?string {
    return $this->selected;
  }

  /**
   * Withhold the end of the form, and say why.
   *
   * @param string|null $reason
   *   The reason, or NULL to allow it again.
   *
   * @return static
   *   The block.
   */
  public function refuse(?string $reason): static {
    $this->refusal = $reason === NULL ? NULL : Ansi::sanitize($reason);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function refusal(): ?string {
    return $this->refusal;
  }

  /**
   * {@inheritdoc}
   */
  public function activate(): bool {
    // Refusing is the whole point of withholding the submit: the button stays
    // unpressed and the reason stands until whatever it names is answered.
    if ($this->refusal !== NULL || $this->selected === NULL) {
      return FALSE;
    }

    $this->activated = $this->selected;

    return TRUE;
  }

  /**
   * The button that was pressed.
   *
   * @return string|null
   *   The name, or NULL while none was.
   */
  public function activated(): ?string {
    return $this->activated;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, ActionsElementsInterface::class, 'actions');
    $parts = [];

    foreach ($this->buttons as $name => $label) {
      // Which button would be pressed is a question about where the cursor is,
      // so a row nobody is on marks none of them: pressing anything from there
      // is not something the reader is one key away from doing.
      $parts[] = $this->isFocused() && $name === $this->selected ? $elements->actionSelected($label) : $elements->actionButton($label);
    }

    $buttons = $elements->actionSelector($this->isFocused()) . ' ' . implode($elements->actionSeparator(), $parts);

    // Against the buttons rather than a row away from them: the reason is what
    // the button now says, so nothing is allowed to stand between the two - and
    // it stands off the mark's column, because the mark says where the cursor
    // is and the reason is not somewhere the cursor can be.
    if ($this->refusal !== NULL) {
      return $elements->actionSelector(FALSE) . ' ' . $elements->actionRefusal(Translator::t($this->refusal)) . "\n" . $buttons;
    }

    return $buttons;
  }

}
