<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget\Capability;

use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Enforces a multi-value field's minimum and maximum selection counts.
 *
 * Mirrors the number widget's bounds check: an accept whose selection count
 * falls outside the declared range is rejected inline with the offending
 * bound, and the bound is surfaced as a persistent hint so it is visible
 * before it is reached. Composes onto a list-collecting widget that accepts
 * through {@see \DrevOps\Tui\Widget\AbstractWidget::accept()}.
 *
 * @package DrevOps\Tui\Widget\Capability
 */
trait SelectionBoundedTrait {

  /**
   * The declared selection-count bounds, or NULL when unbounded.
   */
  protected ?SelectionBounds $selectionBounds = NULL;

  /**
   * {@inheritdoc}
   *
   * Reject a selection count outside the declared range before the value is
   * accepted, naming the offending bound in the inline error.
   */
  #[\Override]
  protected function accept(mixed $value): bool {
    $error = $this->selectionBoundsError($value);
    if ($error !== NULL) {
      $this->error = $error;

      return FALSE;
    }

    return parent::accept($value);
  }

  /**
   * The inline error for a selection count outside the declared range, if any.
   *
   * The wording lives here once so a widget that layers its own accept checks
   * on top (the file picker's type/size limits) can reuse the count check
   * without restating it.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return string|null
   *   The error message when the count is out of range, else NULL.
   */
  protected function selectionBoundsError(mixed $value): ?string {
    $violation = $this->selectionBounds?->violation($value);

    return $violation === NULL ? NULL : Translator::t('Select @constraint.', ['@constraint' => $violation]);
  }

  /**
   * The themed selection-count hint line, or an empty string when not shown.
   *
   * Reuses the accept-time wording so the persistent guidance and the inline
   * error read the same; the hint gives way to the error line while a
   * violation is showing, so the two never stack.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The dim bound line (e.g. "Select at least 2 items."), or '' when there
   *   are no bounds or an error is already showing.
   */
  protected function selectionHint(ThemeInterface $theme): string {
    if (!$this->selectionBounds instanceof SelectionBounds || $this->error !== NULL) {
      return '';
    }

    return $theme->description(Translator::t('Select @constraint.', ['@constraint' => $this->selectionBounds->describe()]));
  }

  /**
   * Append the selection-count hint line beneath a view, when it is shown.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $view
   *   The rendered view.
   *
   * @return string
   *   The view, with the hint line below it when bounds are present.
   */
  protected function withSelectionHint(ThemeInterface $theme, string $view): string {
    $hint = $this->selectionHint($theme);

    return $hint === '' ? $view : $view . "\n" . $hint;
  }

}
