<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\ActionsElementsInterface;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * The buttons that end the form.
 *
 * Submit, cancel, and any the form declares. It is the only block other than a
 * field that refuses anything - the submit is withheld while a required field
 * empty - and unlike a field it holds no value while doing so.
 *
 * @package DrevOps\Tui\Block
 */
final class Actions implements BlockInterface {

  /**
   * The buttons, keyed by name, in declaration order.
   *
   * @var array<string,string>
   */
  protected array $buttons = [];

  /**
   * The name of the button with focus, if any has it.
   */
  protected ?string $focused = NULL;

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
   * @return $this
   *   The block.
   */
  public function action(string $name, string $label): self {
    $this->buttons[$name] = $label;
    $this->focused ??= $name;

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
   * Give a button focus.
   *
   * @param string $name
   *   The button name.
   *
   * @return $this
   *   The block.
   */
  public function focus(string $name): self {
    if (!isset($this->buttons[$name])) {
      throw new \InvalidArgumentException(sprintf('Unknown action "%s". This block declares: %s.', $name, implode(', ', $this->names())));
    }

    $this->focused = $name;

    return $this;
  }

  /**
   * Withhold the end of the form, and say why.
   *
   * @param string|null $reason
   *   The reason, or NULL to allow it again.
   *
   * @return $this
   *   The block.
   */
  public function refuse(?string $reason): self {
    $this->refusal = $reason;

    return $this;
  }

  /**
   * The reason the form cannot be ended yet.
   *
   * @return string|null
   *   The reason, or NULL when nothing is withheld.
   */
  public function refusal(): ?string {
    return $this->refusal;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    if (!$theme instanceof ActionsElementsInterface) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw actions: it does not implement %s.', $theme::class, ActionsElementsInterface::class));
    }

    $parts = [];

    foreach ($this->buttons as $name => $label) {
      $parts[] = $name === $this->focused ? $theme->actionSelected($label) : $theme->actionButton($label);
    }

    return implode($theme->actionSeparator(), $parts);
  }

}
