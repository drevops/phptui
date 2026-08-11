<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Says what a multi-value field's declared selection counts ask for.
 *
 * The bound is surfaced as a persistent hint so it is visible before it is
 * reached; refusing a count that misses it belongs to the block holding the
 * answer, which measures every offered value once.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait SelectionBoundedTrait {

  /**
   * The declared selection-count bounds, or NULL when unbounded.
   */
  protected ?SelectionBounds $selectionBounds = NULL;

  /**
   * The themed selection-count hint line, or an empty string when not shown.
   *
   * Worded as the refusal is, so the persistent guidance and the reason a
   * count was refused read the same; the hint gives way to the error line
   * while one is showing, so the two never stack.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The bound line (e.g. "Select at least 2 items."), or '' when there are
   *   no bounds or an error is already showing.
   */
  protected function selectionHint(ThemeInterface $theme): string {
    if (!$this->selectionBounds instanceof SelectionBounds || $this->error !== NULL) {
      return '';
    }

    // The guidance voice, not the description's: this line states what the
    // field expects, and drawn as a description it is indistinguishable from
    // the highlighted option's own text sitting directly above it.
    return $this->elements($theme)->fieldConstraint(Translator::t('Select @constraint.', ['@constraint' => $this->selectionBounds->describe()]));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function renderConstraint(ThemeInterface $theme): string {
    return $this->selectionHint($theme);
  }

}
