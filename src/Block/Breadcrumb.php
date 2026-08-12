<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Block\Element\BreadcrumbElementsInterface;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Translation\Translator;

/**
 * The trail of panels you have entered.
 *
 * It gains a segment as you descend and loses one as you come back, so its
 * content changes while the block itself never moves.
 *
 * @package DrevOps\PhpTui\Block
 */
final class Breadcrumb extends AbstractBlock {

  /**
   * The segments, from the root to where you are.
   *
   * @var list<string>
   */
  protected array $segments;

  /**
   * Construct a breadcrumb.
   *
   * @param string ...$segments
   *   The panel titles, from the root to where you are.
   */
  public function __construct(string ...$segments) {
    $this->trail(...$segments);
  }

  /**
   * The trail this breadcrumb draws.
   *
   * @param string ...$segments
   *   The panel titles, from the root to where you are.
   *
   * @return static
   *   The block.
   */
  public function trail(string ...$segments): static {
    $this->segments = array_map(Ansi::sanitize(...), array_values($segments));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, BreadcrumbElementsInterface::class, 'a breadcrumb');
    // A trail is made of the panel titles a form declared, so each resolves
    // through the active language exactly as it does on the row it names.
    $labels = array_map(static fn(string $segment): string => $elements->breadcrumbLabel(Translator::t($segment)), $this->segments);

    return implode(' ' . $elements->breadcrumbSeparator() . ' ', $labels);
  }

}
