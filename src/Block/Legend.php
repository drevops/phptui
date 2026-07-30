<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\LegendElementsInterface;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * The keys that apply right now.
 *
 * It rewrites itself as focus moves, so an open field lists different keys from
 * the panel around it.
 *
 * @package DrevOps\Tui\Block
 */
final class Legend extends AbstractBlock {

  /**
   * The entries, each a key and what it does.
   *
   * @var list<array{key:string,does:string}>
   */
  protected array $entries = [];

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
   * Forget every key advertised so far.
   *
   * @return $this
   *   The block.
   */
  public function clear(): self {
    $this->entries = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, LegendElementsInterface::class, 'a legend');
    $parts = [];

    foreach ($this->entries as $entry) {
      $does = Translator::t('to @action', ['@action' => Translator::t($entry['does'])]);
      $parts[] = $elements->legendKey($entry['key']) . ' ' . $elements->legendDescription($does);
    }

    return implode(' ' . $elements->legendSeparator() . ' ', $parts);
  }

}
