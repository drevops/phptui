<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\LegendElementsInterface;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * The keys that apply right now.
 *
 * It rewrites itself as focus moves, so an open field lists different keys from
 * the panel around it.
 *
 * A key written down twice - once where it is bound and once where it is
 * advertised - is a key that drifts. So a legend is written from the bindings
 * themselves and never by hand: it is handed the map a key press resolves
 * against and the fragments naming what those keys do, and reads the glyphs
 * back out of it.
 *
 * @package DrevOps\Tui\Block
 */
final class Legend extends AbstractBlock {

  /**
   * The bindings the keys are read out of, once it has some.
   */
  protected ?ScopedKeyMap $keyMap = NULL;

  /**
   * What the bound keys do, in the order they are advertised.
   *
   * @var list<\DrevOps\Tui\Input\Hint>
   */
  protected array $hints = [];

  /**
   * Advertise whatever a set of bindings makes live.
   *
   * @param \DrevOps\Tui\Input\ScopedKeyMap $keys
   *   The bindings a key press resolves against.
   * @param \DrevOps\Tui\Input\Hint ...$hints
   *   What those keys do, each naming the actions whose keys illustrate it.
   *
   * @return $this
   *   The block.
   */
  public function advertise(ScopedKeyMap $keys, Hint ...$hints): self {
    $this->clear();
    $this->keyMap = $keys;
    $this->hints = array_values($hints);

    return $this;
  }

  /**
   * Forget every key advertised so far.
   *
   * @return $this
   *   The block.
   */
  public function clear(): self {
    $this->keyMap = NULL;
    $this->hints = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, LegendElementsInterface::class, 'a legend');
    $separator = ' ' . $elements->legendSeparator() . ' ';
    $joint = Ansi::width($separator);
    $parts = [];
    $width = 0;

    foreach ($this->written($theme) as $entry) {
      $does = Translator::t('to @action', ['@action' => Translator::t($entry['does'])]);
      $part = $elements->legendKey($entry['key']) . ' ' . $elements->legendDescription($does);
      $taken = $parts === [] ? Ansi::width($part) : $width + $joint + Ansi::width($part);

      // A hint cut mid-word reads as a different word, so a legend out of room
      // drops whole hints from the end - the earliest are the ones a reader
      // reaches for first - keeping at least one however narrow the frame.
      if ($parts !== [] && $taken > $theme->contentWidth()) {
        break;
      }

      $parts[] = $part;
      $width = $taken;
    }

    return implode($separator, $parts);
  }

  /**
   * The entries to draw, one per fragment the bindings can illustrate.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme, which is what says how a key is written.
   *
   * @return list<array{key:string,does:string}>
   *   The entries.
   */
  protected function written(ThemeInterface $theme): array {
    $out = [];

    foreach ($this->hints as $hint) {
      $glyphs = $this->glyphs($theme, $hint);

      // An action nothing reaches has nothing to advertise, so its fragment is
      // dropped rather than drawn as a label with no key in front of it.
      if ($glyphs === '') {
        continue;
      }

      $out[] = ['key' => $glyphs, 'does' => $hint->label];
    }

    return $out;
  }

  /**
   * The keys illustrating one fragment, as the theme writes them.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Input\Hint $hint
   *   The fragment.
   *
   * @return string
   *   The keys, empty when none of its actions is bound here.
   */
  protected function glyphs(ThemeInterface $theme, Hint $hint): string {
    $glyphs = [];

    foreach ($hint->actions as $action) {
      $key = $this->keyMap?->primary($action);

      if ($key instanceof Key) {
        // A named key travels as its name and a typed one as what it writes,
        // so nothing an input layer holds a key in reaches the theme.
        $glyphs[] = $theme->keyGlyph($key->name ?? $key->label());
      }
    }

    return implode('/', $glyphs);
  }

}
