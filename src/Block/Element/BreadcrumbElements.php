<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the breadcrumb block composes.
 *
 * A block names the elements it needs so a theme that cannot draw it fails as a
 * type error rather than a blank line, and so adding a block adds an interface
 * instead of growing one theme class that knows about everything.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface BreadcrumbElements {

  /**
   * Style one segment of the trail.
   *
   * @param string $text
   *   The panel title.
   *
   * @return string
   *   The styled segment.
   */
  public function breadcrumbLabel(string $text): string;

  /**
   * The mark standing between two segments.
   *
   * @return string
   *   The styled separator.
   */
  public function breadcrumbSeparator(): string;

}
