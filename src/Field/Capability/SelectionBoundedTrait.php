<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field\Capability;

use DrevOps\PhpTui\Block\SelectionBounds;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Translation\Translator;

/**
 * Presents a multi-value field's declared selection-count bounds.
 *
 * The bound is shown as a persistent hint, so it is visible before it is
 * reached. Rejecting a count outside the bounds belongs to the block holding
 * the answer, which measures every offered value once.
 *
 * @package DrevOps\PhpTui\Field\Capability
 */
trait SelectionBoundedTrait {

  /**
   * The declared selection-count bounds, or NULL when unbounded.
   */
  protected ?SelectionBounds $selectionBounds = NULL;

  /**
   * The themed selection-count hint line, or an empty string when not shown.
   *
   * The hint uses the refusal's wording, so the persistent guidance and the
   * rejection message match. While an error shows, the hint is suppressed, so
   * the two lines never stack.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
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

    // Rendered as a constraint, not a description: the line states what the
    // field expects. Drawn as a description it is indistinguishable from the
    // highlighted option's own text directly above it.
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
