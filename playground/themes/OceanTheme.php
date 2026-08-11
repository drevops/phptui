<?php

declare(strict_types=1);

namespace Playground\Themes;

use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Sgr;

/**
 * A custom theme that overrides as much as it sensibly can.
 *
 * It demonstrates the two sizes of override, both shown below:
 *  - the palette - one protected method per hue (accent(), value(),
 *    description()…), repainting every element drawn from it at once;
 *  - the per-block elements a block composes its row from (fieldLabel(),
 *    panelTitle(), breadcrumbLabel()…), each restyled on its own.
 *
 * It extends DefaultTheme, so anything left un-overridden falls back to the
 * default theme, including its dark/light mode. Select it with its class name
 * (`\Playground\Themes\OceanTheme`), or register a short name with
 * ThemeManager::register('ocean', OceanTheme::class).
 */
class OceanTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function accent(): string {
    return Sgr::of(Sgr::Bold, Sgr::BrightCyan);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function value(string $text, bool $emphatic = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::BrightCyan), $emphatic), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function description(string $text): string {
    return $this->paint(Sgr::of(Sgr::Blue), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::Cyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function marker(bool $selected): string {
    return $selected ? $this->paint($this->accent(), $this->isUnicode() ? '➤' : '>') : ' ';
  }

  /**
   * The mark this theme stands between one thing and the next.
   *
   * @return string
   *   The mark.
   */
  protected function divider(): string {
    return '/';
  }

  /**
   * The mark this theme leads a following-on line with.
   *
   * @return string
   *   The mark.
   */
  protected function lead(): string {
    return $this->isUnicode() ? '•' : '*';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function keyGlyph(KeyName|string $key): string {
    if ($key === KeyName::Enter) {
      return $this->isUnicode() ? '⏎' : '<';
    }

    return parent::keyGlyph($key);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function chromeOverflowMarker(bool $above): string {
    return $this->indicator($above ? ($this->isUnicode() ? '▴' : '^') : ($this->isUnicode() ? '▾' : 'v'));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldBadge(string $text): string {
    return $this->paint(Sgr::of(Sgr::Reverse, Sgr::Cyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldCaret(): string {
    return $this->paint($this->accent(), $this->isUnicode() ? '▎' : '|');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldOptionMarker(bool $chosen, bool $exclusive = FALSE): string {
    if ($exclusive) {
      return $chosen ? $this->paint($this->accent(), $this->isUnicode() ? '◉' : '(o)') : ($this->isUnicode() ? '◯' : '( )');
    }

    return $chosen ? $this->fieldValue($this->isUnicode() ? '▣' : '[x]') : ($this->isUnicode() ? '▢' : '[ ]');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldLabel(string $text): string {
    return $this->label($text) . ':';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function fieldDescription(string $text): string {
    return $this->description($this->lead() . ' ' . $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionSelected(string $label): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Reverse, Sgr::BrightCyan), '« ' . $label . ' »');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionButton(string $label): string {
    return $this->label('« ' . $label . ' »');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function actionSeparator(): string {
    return '   ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelDescend(): string {
    return $this->description($this->isUnicode() ? '»' : '>');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelDescription(string $text): string {
    return $this->description($this->lead() . ' ' . $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelSummary(string $text): string {
    return $this->description(($this->isUnicode() ? '»' : '>') . ' ' . $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function panelSummarySeparator(): string {
    return $this->divider();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbLabel(string $text): string {
    return $this->paint(Sgr::of(Sgr::Cyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumbSeparator(): string {
    return $this->breadcrumbLabel($this->divider());
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function legendSeparator(): string {
    return $this->footer('  ' . $this->lead() . '  ');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderBanner(string $logo, string $version): array {
    $lines = [];

    foreach (explode("\n", $logo) as $line) {
      $lines[] = $this->markupTitle($line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->footer('≈ ' . $version . ' ≈');
    }

    return $lines;
  }

}
