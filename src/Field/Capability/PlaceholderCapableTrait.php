<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Ghost text over an empty input, in the theme's ghost style.
 *
 * The placeholder and an inline completion suffix share one visual channel and
 * never apply at once: a completion needs something typed, a placeholder needs
 * nothing typed.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait PlaceholderCapableTrait {

  /**
   * The ghost text shown while the input is empty.
   */
  protected string $placeholder = '';

  /**
   * {@inheritdoc}
   */
  public function setPlaceholder(string $placeholder): static {
    $this->placeholder = $placeholder;

    return $this;
  }

  /**
   * The placeholder text for the current input, unstyled.
   *
   * @param string $current
   *   The input the placeholder stands in for.
   *
   * @return string
   *   The localized placeholder, or an empty string when the input carries
   *   something or no placeholder was declared.
   */
  protected function placeholderText(string $current): string {
    if ($current !== '' || $this->placeholder === '') {
      return '';
    }

    return Translator::t($this->placeholder);
  }

  /**
   * The placeholder text for the current input, in the theme's ghost style.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme supplying the ghost styling.
   * @param string $current
   *   The input the placeholder stands in for.
   *
   * @return string
   *   The styled placeholder, or an empty string when none applies - including
   *   in no-colour mode, where ghost text would read as a typed value.
   */
  protected function placeholderGhost(ThemeInterface $theme, string $current): string {
    $text = $this->placeholderText($current);

    return $text === '' ? '' : $theme->ghost($text);
  }

}
