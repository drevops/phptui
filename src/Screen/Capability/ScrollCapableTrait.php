<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Screen\Capability;

/**
 * Holds a surface's offset; scrollTo() throws for one that does not scroll.
 *
 * @package DrevOps\PhpTui\Screen\Capability
 */
trait ScrollCapableTrait {

  /**
   * Whether this surface's contents may overflow it.
   */
  protected bool $scrolls = FALSE;

  /**
   * The first row of its contents that is visible.
   */
  protected int $offset = 0;

  /**
   * {@inheritdoc}
   */
  public function scrolls(): static {
    $this->scrolls = TRUE;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isScrolling(): bool {
    return $this->scrolls;
  }

  /**
   * {@inheritdoc}
   */
  public function scrollTo(int $row): static {
    if (!$this->scrolls) {
      throw new \LogicException(sprintf('%s does not scroll, so it cannot be scrolled to row %d.', $this->surface(), $row));
    }

    $this->offset = max(0, $row);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function offset(int $content, int $visible): int {
    return min($this->offset, max(0, $content - $visible));
  }

  /**
   * The name of this surface, used in the scrollTo() exception message.
   *
   * @return string
   *   The name.
   */
  protected function surface(): string {
    return static::class;
  }

}
