<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\LegendElementsInterface;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\ScopedKeyMap;
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
 * themselves: it is handed the map a key press resolves against and the
 * fragments naming what those keys do, and reads the glyphs back out of it.
 *
 * @package DrevOps\Tui\Block
 */
final class Legend extends AbstractBlock {

  /**
   * The entries written out by hand, each a key and what it does.
   *
   * @var list<array{key:string,does:string}>
   */
  protected array $entries = [];

  /**
   * The bindings the entries are read out of, once it has some.
   */
  protected ?ScopedKeyMap $keyMap = NULL;

  /**
   * What the bound keys do, in the order they are advertised.
   *
   * @var list<\DrevOps\Tui\Input\Hint>
   */
  protected array $hints = [];

  /**
   * Advertise a key.
   *
   * @param string $key
   *   The key as it is written.
   * @param string $does
   *   What pressing it does.
   *
   * @return $this
   *   The block.
   */
  public function entry(string $key, string $does): self {
    $this->entries[] = ['key' => $key, 'does' => $does];

    return $this;
  }

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
    $this->entries = [];
    $this->keyMap = NULL;
    $this->hints = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, LegendElementsInterface::class, 'a legend');
    $parts = [];

    foreach ($this->written($theme) as $entry) {
      $does = Translator::t('to @action', ['@action' => Translator::t($entry['does'])]);
      $parts[] = $elements->legendKey($entry['key']) . ' ' . $elements->legendDescription($does);
    }

    return implode(' ' . $elements->legendSeparator() . ' ', $parts);
  }

  /**
   * The entries to draw: the ones written by hand, else the bound ones.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme, which is what says how a key is written.
   *
   * @return list<array{key:string,does:string}>
   *   The entries.
   */
  protected function written(ThemeInterface $theme): array {
    if (!$this->keyMap instanceof ScopedKeyMap) {
      return $this->entries;
    }

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
        $glyphs[] = $theme->keyGlyph($key);
      }
    }

    return implode('/', $glyphs);
  }

}
