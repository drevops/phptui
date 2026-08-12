<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Theme\Capability;

/**
 * A theme that draws the markdown subset rather than the markers themselves.
 *
 * Links are recognised whatever a theme declares, because a target a terminal
 * could open is address rather than decoration. The rest of the subset - what
 * is emphatic, what is code, what is a list - is styling, so it is drawn only
 * where a theme says it draws it, and left as the literal text elsewhere.
 *
 * @package DrevOps\PhpTui\Theme\Capability
 */
interface MarkdownCapableInterface {

  /**
   * Whether the markdown subset is drawn rather than left as literal text.
   *
   * @return bool
   *   TRUE when it is drawn.
   */
  public function isMarkdown(): bool;

}
