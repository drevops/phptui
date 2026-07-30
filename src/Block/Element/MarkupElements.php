<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block\Element;

/**
 * The elements the markup block composes.
 *
 * @package DrevOps\Tui\Block\Element
 */
interface MarkupElements {

  /**
   * Style the title above a body of markup.
   *
   * @param string $text
   *   The title.
   *
   * @return string
   *   The styled title.
   */
  public function markupTitle(string $text): string;

  /**
   * Style one line of a body of markup.
   *
   * @param string $text
   *   The line.
   *
   * @return string
   *   The styled line.
   */
  public function markupBody(string $text): string;

}
