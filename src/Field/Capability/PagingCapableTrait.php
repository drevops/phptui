<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Block\Element\FieldElementsInterface;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Viewport;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Paging behaviour: window a long list to a page that follows the cursor.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait PagingCapableTrait {

  /**
   * The page size applied when a field declares none.
   */
  public const int DEFAULT_PAGE_SIZE = 10;

  /**
   * The number of rows shown at once before the list pages.
   */
  protected int $pageSize = self::DEFAULT_PAGE_SIZE;

  /**
   * The index of the first visible row under paging.
   */
  protected int $offset = 0;

  /**
   * The number of rows shown at once before the list pages.
   *
   * @return int
   *   The effective page size.
   */
  public function pageSize(): int {
    return $this->pageSize;
  }

  /**
   * Resolve the effective page size, rejecting a non-positive declared value.
   *
   * The builder rejects a non-positive page size, but a field may be
   * constructed directly, so the invariant is enforced here too.
   *
   * @param int|null $page_size
   *   The declared page size, or NULL to use the default.
   *
   * @return int
   *   The effective page size.
   *
   * @throws \InvalidArgumentException
   *   When a declared page size is not positive.
   */
  protected function resolvePageSize(?int $page_size): int {
    if ($page_size !== NULL && $page_size < 1) {
      throw new \InvalidArgumentException(Translator::t('Page size must be a positive integer, @size given.', [
        '@size' => $page_size,
      ]));
    }

    return $page_size ?? self::DEFAULT_PAGE_SIZE;
  }

  /**
   * Compute the cursor-visible paging window, storing its offset.
   *
   * @param int $total
   *   The total number of option rows.
   * @param int $cursor
   *   The cursor row index (a negative cursor pins the window to the top).
   *
   * @return \DrevOps\Tui\Render\Viewport
   *   The window: its offset and whether rows are scrolled off above or below.
   */
  protected function pageViewport(int $total, int $cursor): Viewport {
    $viewport = (new Scroller())->follow($total, $this->pageSize, max(0, $cursor), $this->offset);
    $this->offset = $viewport->offset;

    return $viewport;
  }

  /**
   * Wrap rendered rows with the scroll indicators for a paging window.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param list<string> $rows
   *   The rendered visible rows.
   * @param \DrevOps\Tui\Render\Viewport $viewport
   *   The paging window.
   *
   * @return list<string>
   *   The rows, with an indicator line for each scrolled-off side.
   */
  protected function wrapScrolled(ThemeInterface $theme, array $rows, Viewport $viewport): array {
    $lines = [];

    if ($viewport->hasAbove) {
      $lines[] = '  ' . $this->overflow($theme)->fieldOverflowMarker(TRUE);
    }

    $lines = array_merge($lines, $rows);

    if ($viewport->hasBelow) {
      $lines[] = '  ' . $this->overflow($theme)->fieldOverflowMarker(FALSE);
    }

    return $lines;
  }

  /**
   * The theme, narrowed to the mark that says a list runs past its page.
   *
   * The field's own mark rather than the chrome's: a field draws only what it
   * owns, so the page it windows a list to is marked with an element of its
   * own, and a theme that wants the two to read alike says so once in each.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\Tui\Block\Element\FieldElementsInterface
   *   The theme, able to draw the mark.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   */
  protected function overflow(ThemeInterface $theme): FieldElementsInterface {
    if (!$theme instanceof FieldElementsInterface) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw an overflow mark: it does not implement %s.', $theme::class, FieldElementsInterface::class));
    }

    return $theme;
  }

}
