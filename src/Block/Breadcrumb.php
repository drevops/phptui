<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\BreadcrumbElements;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * The trail of panels you have entered.
 *
 * It gains a segment as you descend and loses one as you come back, so its
 * content changes while the block itself never moves.
 *
 * @package DrevOps\Tui\Block
 */
final class Breadcrumb implements BlockInterface {

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
    $this->segments = array_values($segments);
  }

  /**
   * The trail this breadcrumb draws.
   *
   * @param string ...$segments
   *   The panel titles, from the root to where you are.
   *
   * @return $this
   *   The block.
   */
  public function trail(string ...$segments): self {
    $this->segments = array_values($segments);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    if (!$theme instanceof BreadcrumbElements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw a breadcrumb: it does not implement %s.', $theme::class, BreadcrumbElements::class));
    }

    $labels = array_map(static fn(string $segment): string => $theme->breadcrumbLabel($segment), $this->segments);

    return implode(' ' . $theme->breadcrumbSeparator() . ' ', $labels);
  }

}
