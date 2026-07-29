<?php

declare(strict_types=1);

namespace Playground\Themes;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Navigator;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Sgr;

/**
 * A custom theme that overrides as much as it sensibly can.
 *
 * It demonstrates two kinds of override, both shown below:
 *  - the appearance atoms - one method per colour and glyph (title(), value(),
 *    marker(), arrow()…), each overridden on its own;
 *  - any render*() and summarizePanel() method - to change how an element is
 *    laid out from those atoms.
 *
 * It extends DefaultTheme, so anything left un-overridden (e.g. renderBody(),
 * renderFrame()) falls back to the default theme, including its dark/light
 * mode. Select it with its class name (`\Playground\Themes\OceanTheme`), or
 * register a short name with ThemeManager::register('ocean',
 * OceanTheme::class).
 */
class OceanTheme extends DefaultTheme {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function title(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function value(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::BrightCyan), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function description(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Blue), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function badge(string $text, bool $selected = FALSE): string {
    return $this->paint($this->emphasize(Sgr::of(Sgr::Reverse, Sgr::Cyan), $selected), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function breadcrumb(string $text): string {
    return $this->paint(Sgr::of(Sgr::Cyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function footer(string $text): string {
    return $this->paint(Sgr::of(Sgr::Cyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function cursor(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::Reverse, Sgr::BrightCyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicator(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function highlight(string $text): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $text);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function marker(bool $selected): string {
    return $selected ? $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $this->hasUnicode() ? '➤' : '>') : ' ';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function arrow(): string {
    return $this->hasUnicode() ? '»' : '>';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function separator(): string {
    return '/';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function enter(): string {
    return $this->hasUnicode() ? '⏎' : '<';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function dot(): string {
    return $this->hasUnicode() ? '•' : '*';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicatorUp(): string {
    return $this->hasUnicode() ? '▴' : '^';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function indicatorDown(): string {
    return $this->hasUnicode() ? '▾' : 'v';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function radio(bool $on): string {
    return $on ? $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $this->hasUnicode() ? '◉' : '(o)') : ($this->hasUnicode() ? '◯' : '( )');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function check(bool $on): string {
    return $on ? $this->value($this->hasUnicode() ? '▣' : '[x]') : ($this->hasUnicode() ? '▢' : '[ ]');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function caret(): string {
    return $this->paint(Sgr::of(Sgr::Bold, Sgr::BrightCyan), $this->hasUnicode() ? '▎' : '|');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderFieldLine(Field $field, Answers $answers, bool $selected): array {
    $prefix = $this->marker($selected) . ' ' . $this->label($field->label) . ': ';
    $indent = str_repeat(' ', Ansi::width($prefix));

    // A multi-line value (a textarea) lays out one row per line: the first
    // rides the label row, the rest align under the value column, so no row
    // carries an embedded newline.
    $lines = [];

    foreach (explode("\n", $this->normalizeLines($this->renderFieldValue($field, $answers->value($field->id)))) as $index => $value_line) {
      $lines[] = ($index === 0 ? $prefix : $indent) . $this->value($value_line);
    }

    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderPanelLine(Panel $panel, bool $selected): string {
    $count = count($panel->fields) + count($panel->panels);

    return $this->marker($selected) . ' ' . $this->title($panel->title) . '  ' . $this->description($this->arrow() . ' ' . $count . ' item' . ($count === 1 ? '' : 's'));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderDescriptionLine(string $description, bool $selected): string {
    return '    ' . $this->description($this->dot() . ' ' . $description, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function summarizePanel(Panel $panel, Answers $answers): string {
    $parts = [];

    foreach ($panel->fields as $field) {
      if ($answers->has($field->id)) {
        // A summary is one line, so a multi-line value folds to a single row.
        $parts[] = str_replace("\n", ' ', $this->normalizeLines($this->renderFieldValue($field, $answers->value($field->id))));
      }
    }

    return implode(' ' . $this->separator() . ' ', array_slice($parts, 0, 3));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderSummaryLine(string $summary, bool $selected): string {
    return '    ' . $this->description($this->arrow() . ' ' . $summary, $selected);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderBreadcrumbLine(Navigator $navigator): string {
    return $this->breadcrumb('≈ ' . implode(' ' . $this->separator() . ' ', $navigator->breadcrumb()));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderHints(ScopedKeyMap $keys, Hint ...$hints): string {
    $sep = '  ' . $this->dot() . '  ';

    $fragments = array_filter(array_map(fn(Hint $hint): string => $this->keysHint($keys, $hint->label, ...$hint->actions), $hints));

    return $this->footer(implode($sep, $fragments));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderButtonBar(array $labels, int $selected): string {
    $buttons = [];

    foreach ($labels as $index => $label) {
      $text = '« ' . $label . ' »';
      $buttons[] = $index === $selected ? $this->cursor($text) : $this->label($text);
    }

    return '  ' . implode('   ', $buttons);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function renderBanner(string $logo, string $version): string {
    $lines = [];

    foreach (explode("\n", $logo) as $line) {
      $lines[] = $this->title($line);
    }

    if ($version !== '') {
      $lines[] = '';
      $lines[] = $this->footer('≈ ' . $version . ' ≈');
    }

    return implode("\n", $lines);
  }

}
