<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\LegendElements;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * The keys that apply right now.
 *
 * It rewrites itself as focus moves, so an open field lists different keys from
 * the panel around it.
 *
 * @package DrevOps\Tui\Block
 */
final class Legend implements BlockInterface {

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
    if (!$theme instanceof LegendElements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw a legend: it does not implement %s.', $theme::class, LegendElements::class));
    }

    $parts = [];

    foreach ($this->entries as $entry) {
      $parts[] = $theme->legendKey($entry['key']) . ' ' . $theme->legendDescription('to ' . $entry['does']);
    }

    return implode(' ' . $theme->legendSeparator() . ' ', $parts);
  }

}
