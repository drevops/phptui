<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field\Capability;

use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Choice behaviour over the option rows, single-value or multiple-value.
 *
 * Composes with {@see OptionsCapableTrait}: the cursor moves over the visible
 * rows, only ever resting on a selectable one. A single-value field commits
 * the highlighted option; a multiple-value field toggles a selection set -
 * Space toggles the highlighted option, Left/Right deselect or select every
 * visible option - and commits the selected values in display order.
 *
 * @package DrevOps\Tui\Field\Capability
 */
trait SelectionCapableTrait {

  /**
   * The highlighted index within the visible rows.
   */
  protected int $cursor = 0;

  /**
   * The selected values as a set (value => TRUE); used when multiple.
   *
   * @var array<string,bool>
   */
  protected array $selected = [];

  /**
   * Whether the field collects several values rather than one.
   */
  protected bool $multiple = FALSE;

  /**
   * The field type this field binds its keys under.
   *
   * @return \DrevOps\Tui\Block\FieldType
   *   The choice field type.
   */
  abstract protected function choiceType(): FieldType;

  /**
   * The matched-character positions in a label, for highlighting.
   *
   * @param string $label
   *   The option label.
   *
   * @return list<int>
   *   The matched indices.
   */
  abstract protected function matchPositions(string $label): array;

  /**
   * Seed the option rows and the cursor, and the selection set when multiple.
   *
   * @param array<int|string,\DrevOps\Tui\Block\Option|string> $options
   *   Option rows in display order - a list of options or the value => label
   *   shorthand map.
   * @param string|list<string> $default
   *   The initially highlighted value (single) or selected values (multiple);
   *   non-selectable values are ignored.
   * @param bool $multiple
   *   Whether the field collects several values.
   */
  protected function initChoice(array $options, string|array $default, bool $multiple): void {
    $this->multiple = $multiple;
    $this->initOptions($options);

    if (!$multiple) {
      $this->cursor = $this->cursorForDefault($this->options, is_string($default) ? $default : '');

      return;
    }

    $selectable = array_fill_keys($this->selectableValues(), TRUE);
    foreach (is_array($default) ? $default : [] as $value) {
      if (isset($selectable[$value])) {
        $this->selected[$value] = TRUE;
      }
    }

    $this->cursor = $this->firstSelectable($this->options);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field($this->choiceType(), $this->multiple);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    if ($this->multiple) {
      if (!$this->handleMultiChoiceKey($key)) {
        $this->handleFilterKey($key);
      }

      return;
    }

    $this->handleSingleMode($key);
  }

  /**
   * Handle a key in single-value mode.
   *
   * The plain single-choice list only navigates and accepts; a filtering
   * single-choice field overrides this to route typed characters to the
   * filter.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   */
  protected function handleSingleMode(Key $key): void {
    $this->handleSingleChoiceKey($key);
  }

  /**
   * Handle cancel, cursor movement and acceptance of the highlighted option.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   */
  protected function handleSingleChoiceKey(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, -1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, 1);

      return;
    }

    if ($keys->matches($key, Action::Accept) && $this->currentSelectable()) {
      $this->accept($this->liveValue());
    }
  }

  /**
   * Handle cancel, acceptance, cursor movement and selection toggles.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to handle.
   *
   * @return bool
   *   TRUE when the key was consumed.
   */
  protected function handleMultiChoiceKey(Key $key): bool {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return TRUE;
    }

    if ($this->handleAccept($key)) {
      return TRUE;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, -1);

      return TRUE;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->cursor = $this->stepCursor($this->visible(), $this->cursor, 1);

      return TRUE;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->toggleCurrent();

      return TRUE;
    }

    if ($keys->matches($key, Action::SelectAll)) {
      $this->setAllVisible(TRUE);

      return TRUE;
    }

    if ($keys->matches($key, Action::SelectNone)) {
      $this->setAllVisible(FALSE);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Whether the highlighted visible row is a selectable option.
   *
   * @return bool
   *   TRUE when the cursor rests on a selectable option.
   */
  public function currentSelectable(): bool {
    return ($this->visible()[$this->cursor] ?? NULL)?->selectable() ?? FALSE;
  }

  /**
   * The selectable option values, in display order.
   *
   * @return list<string>
   *   The selectable option values.
   */
  public function selectableValues(): array {
    return Option::selectableValues($this->options);
  }

  /**
   * Toggle the highlighted option when it is selectable.
   */
  public function toggleCurrent(): void {
    $option = $this->visible()[$this->cursor] ?? NULL;

    if (!$option instanceof Option || !$option->selectable()) {
      return;
    }

    if (isset($this->selected[$option->value])) {
      unset($this->selected[$option->value]);
    }
    else {
      $this->selected[$option->value] = TRUE;
    }
  }

  /**
   * Select or deselect all selectable visible options.
   *
   * @param bool $selected
   *   TRUE to select, FALSE to deselect.
   */
  public function setAllVisible(bool $selected): void {
    foreach ($this->visible() as $option) {
      if (!$option->selectable()) {
        continue;
      }

      if ($selected) {
        $this->selected[$option->value] = TRUE;
      }
      else {
        unset($this->selected[$option->value]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    if (!$this->multiple) {
      return $this->currentSelectable() ? $this->visible()[$this->cursor]->value : '';
    }

    $out = [];

    foreach ($this->options as $option) {
      if ($option->selectable() && isset($this->selected[$option->value])) {
        $out[] = $option->value;
      }
    }

    // The rows are only the display order, and a list that changes under the
    // selection - one resolved from a query - no longer holds everything that
    // was selected from an earlier one. Those selections are still the user's,
    // so they follow the ordered ones rather than being dropped.
    foreach (array_keys($this->selected) as $value) {
      if (!in_array($value, $out, TRUE)) {
        $out[] = $value;
      }
    }

    return $out;
  }

  /**
   * Render one option row: a radio (single) or marker and checkbox (multiple).
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param \DrevOps\Tui\Block\Option $option
   *   The option row.
   * @param bool $current
   *   Whether the row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  public function renderOptionRow(ThemeInterface $theme, Option $option, bool $current): string {
    $elements = $this->elements($theme);

    if ($this->multiple) {
      if ($option->disabled) {
        return $elements->fieldEntrySelector(FALSE) . ' ' . $elements->fieldEntryMarker(FALSE) . ' ' . $this->renderDisabledLabel($theme, $option);
      }

      $chosen = isset($this->selected[$option->value]);

      return $elements->fieldEntrySelector($current) . ' ' . $elements->fieldEntryMarker($chosen) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current, $chosen);
    }

    if ($option->disabled) {
      return $elements->fieldEntryMarker(FALSE, TRUE) . ' ' . $this->renderDisabledLabel($theme, $option);
    }

    // Moving the cursor is what picks in an exclusive list, so the mark and the
    // cursor say the same thing and the row draws only the mark.
    return $elements->fieldEntryMarker($current, TRUE) . ' ' . $this->renderMatchedLabel($theme, $option->label, $this->matchPositions($option->label), $current);
  }

  /**
   * {@inheritdoc}
   *
   * Measured from the glyphs the row actually draws rather than assumed: the
   * leading run differs between the two modes, and again between a themed
   * glyph and its textual stand-in.
   */
  #[\Override]
  protected function entryTextOffset(ThemeInterface $theme): int {
    $elements = $this->elements($theme);

    $prefix = $this->multiple
      ? $elements->fieldEntrySelector(TRUE) . ' ' . $elements->fieldEntryMarker(FALSE) . ' '
      : $elements->fieldEntryMarker(FALSE, TRUE) . ' ';

    return Ansi::width($prefix);
  }

  /**
   * {@inheritdoc}
   *
   * In multiple mode Toggle is the non-obvious action - nothing else signals
   * that a key toggles the highlighted option - so it leads, followed by the
   * rest.
   */
  #[\Override]
  public function hints(): array {
    if (!$this->multiple) {
      return [new Hint('move', Action::MoveUp, Action::MoveDown), ...parent::hints()];
    }

    return [
      new Hint('select', Action::Toggle),
      new Hint('move', Action::MoveUp, Action::MoveDown),
      new Hint('select none or all', Action::SelectNone, Action::SelectAll),
      ...parent::hints(),
    ];
  }

}
