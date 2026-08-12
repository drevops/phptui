<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Field;

use DrevOps\PhpTui\Block\Element\FieldElementsInterface;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Hint;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyMapManager;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\Input\ScopedKeyMap;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Utils\Strings;

/**
 * Shared field behaviour: accepting what was collected, and cancelling.
 *
 * @package DrevOps\PhpTui\Field
 */
abstract class AbstractField implements FieldInterface {

  /**
   * The resolved key bindings for this field's scope.
   *
   * When none are injected, the field falls back to the default preset for
   * its scope.
   */
  protected ?ScopedKeyMap $scoped = NULL;

  /**
   * The fuzzy matcher, created on first use.
   */
  protected ?Matcher $matcher = NULL;

  /**
   * Whether a value has been offered as the answer.
   */
  protected bool $complete = FALSE;

  /**
   * Whether the field was cancelled.
   */
  protected bool $cancelled = FALSE;

  /**
   * The current validation error, if any.
   */
  protected ?string $error = NULL;

  /**
   * The value offered as the answer once complete.
   */
  protected mixed $accepted = NULL;

  /**
   * {@inheritdoc}
   */
  public function isComplete(): bool {
    return $this->complete;
  }

  /**
   * {@inheritdoc}
   */
  public function isCancelled(): bool {
    return $this->cancelled;
  }

  /**
   * {@inheritdoc}
   */
  public function error(): ?string {
    return $this->error;
  }

  /**
   * {@inheritdoc}
   */
  public function refused(string $reason): static {
    $this->error = $reason;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function value(): mixed {
    return $this->complete ? $this->accepted : $this->liveValue();
  }

  /**
   * {@inheritdoc}
   */
  public function hints(): array {
    return [new Hint('accept', Action::Accept), new Hint('cancel', Action::Cancel)];
  }

  /**
   * {@inheritdoc}
   */
  public function setKeys(ScopedKeyMap $keys): static {
    $this->scoped = $keys;

    return $this;
  }

  /**
   * The in-progress value before acceptance.
   *
   * @return mixed
   *   The current, not-yet-accepted value.
   */
  abstract protected function liveValue(): mixed;

  /**
   * The scope whose default bindings apply when none are injected.
   *
   * Fields whose bindings differ from the base defaults override this.
   *
   * @return \DrevOps\PhpTui\Input\Scope
   *   The field's binding scope.
   */
  protected function keyScope(): Scope {
    return Scope::base();
  }

  /**
   * {@inheritdoc}
   */
  public function keys(): ScopedKeyMap {
    return $this->scoped ??= KeyMapManager::create()->scope($this->keyScope());
  }

  /**
   * Cancel the field when the key triggers the cancel action.
   *
   * @param \DrevOps\PhpTui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key cancelled the field.
   */
  protected function handleCancel(Key $key): bool {
    if ($this->keys()->matches($key, Action::Cancel)) {
      $this->cancelled = TRUE;

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Accept the live value when the key triggers the accept action.
   *
   * @param \DrevOps\PhpTui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key triggered the accept and was consumed.
   */
  protected function handleAccept(Key $key): bool {
    if ($this->keys()->matches($key, Action::Accept)) {
      $this->accept($this->liveValue());

      return TRUE;
    }

    return FALSE;
  }

  /**
   * The theme, narrowed to the elements a field draws with.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\PhpTui\Block\Element\FieldElementsInterface
   *   The theme, able to draw a field.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   */
  protected function elements(ThemeInterface $theme): FieldElementsInterface {
    if (!$theme instanceof FieldElementsInterface) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw a field: it does not implement %s.', $theme::class, FieldElementsInterface::class));
    }

    return $theme;
  }

  /**
   * Style one option's label.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   * @param bool $chosen
   *   Whether the option is picked.
   *
   * @return string
   *   The styled label.
   */
  protected function optionLabel(ThemeInterface $theme, string $label, bool $current, bool $chosen = FALSE): string {
    return $this->elements($theme)->fieldOption($label, $chosen, $current);
  }

  /**
   * Render an exclusive option row: the mark and the label beside it.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderExclusiveRow(ThemeInterface $theme, string $label, bool $current): string {
    // Moving the cursor picks in an exclusive list, so the mark and the
    // cursor state coincide and the row draws only the mark.
    return $this->elements($theme)->fieldOptionMarker($current, TRUE) . ' ' . $this->optionLabel($theme, $label, $current);
  }

  /**
   * The shared fuzzy matcher.
   *
   * @return \DrevOps\PhpTui\Field\Matcher
   *   The matcher.
   */
  protected function matcher(): Matcher {
    return $this->matcher ??= new Matcher();
  }

  /**
   * Style an option label, emphasising the query-matched characters.
   *
   * The label is split into runs of matched and unmatched characters, each run
   * styled on its own, so no SGR code nests inside another. With no matched
   * positions this is exactly {@see optionLabel()}.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The option label.
   * @param list<int> $positions
   *   The zero-based indices of the matched characters.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   * @param bool $chosen
   *   Whether the option is picked.
   *
   * @return string
   *   The styled label.
   */
  protected function renderMatchedLabel(ThemeInterface $theme, string $label, array $positions, bool $current, bool $chosen = FALSE): string {
    if ($positions === []) {
      return $this->optionLabel($theme, $label, $current, $chosen);
    }

    $matched = array_fill_keys($positions, TRUE);
    $out = '';
    $run = '';
    $run_matched = FALSE;

    foreach (Strings::split($label) as $index => $char) {
      $is_matched = isset($matched[$index]);

      if ($run !== '' && $is_matched !== $run_matched) {
        $out .= $this->styleRun($theme, $run, $run_matched, $current, $chosen);
        $run = '';
      }

      $run .= $char;
      $run_matched = $is_matched;
    }

    return $out . $this->styleRun($theme, $run, $run_matched, $current, $chosen);
  }

  /**
   * Style one run of matched or unmatched characters.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $run
   *   The run of characters.
   * @param bool $matched
   *   Whether the run's characters matched the query.
   * @param bool $current
   *   Whether the option's row holds the cursor.
   * @param bool $chosen
   *   Whether the option is picked.
   *
   * @return string
   *   The styled run.
   */
  protected function styleRun(ThemeInterface $theme, string $run, bool $matched, bool $current, bool $chosen): string {
    if ($matched) {
      return $this->elements($theme)->fieldOptionMatch($run);
    }

    return $this->optionLabel($theme, $run, $current, $chosen);
  }

  /**
   * {@inheritdoc}
   *
   * The frame every field shares: the field's body, the highlighted option's
   * description (choice fields only), the constraint, then the error. The
   * description changes as the highlight moves, so it renders directly under
   * the list rather than below the constraint, which never moves.
   *
   * A field supplies its body via {@see renderBody()} and its constraint via
   * {@see renderConstraint()}.
   */
  public function view(ThemeInterface $theme): string {
    $lines = [$this->renderBody($theme)];

    $detail = $this->renderOptionDescription($theme, $this->currentDescription());
    if ($detail !== '') {
      $lines[] = $detail;
    }

    $constraint = $this->renderConstraint($theme);
    if ($constraint !== '') {
      $lines[] = $constraint;
    }

    if ($this->error !== NULL) {
      $lines[] = $this->elements($theme)->fieldError($this->error);
    }

    return implode("\n", $lines);
  }

  /**
   * What the field expects of an answer.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The constraint line(s), or an empty string when the field declares no
   *   limits or an error has already replaced them.
   */
  protected function renderConstraint(ThemeInterface $theme): string {
    return '';
  }

  /**
   * The field's own rendered body, before the shared description and error.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The rendered body lines.
   */
  abstract protected function renderBody(ThemeInterface $theme): string;

  /**
   * The highlighted option's description; empty for fields without one.
   *
   * Choice fields override this, directly or via a capability trait; the
   * empty default adds no description line to the frame.
   *
   * @return string
   *   The description shown beneath the body, or an empty string.
   */
  protected function currentDescription(): string {
    return '';
  }

  /**
   * The narrowest content width at which an option description is still shown.
   *
   * Below this width a wrapped description breaks into unreadable fragments,
   * so it is dropped.
   */
  protected const int MIN_DESCRIPTION_WIDTH = 8;

  /**
   * Render an option description, wrapped to the panel width and dimmed.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $description
   *   The description text.
   *
   * @return string
   *   The wrapped, dimmed line(s), or an empty string when there is no
   *   description or the panel is too narrow to show one.
   */
  protected function renderOptionDescription(ThemeInterface $theme, string $description): string {
    // Indent to the column where an option's own text starts, so the
    // description aligns with the option above it.
    $indent = str_repeat(' ', $this->optionTextOffset($theme));
    $width = $theme->contentWidth() - Strings::length($indent);

    if ($description === '' || $width < self::MIN_DESCRIPTION_WIDTH) {
      return '';
    }

    $elements = $this->elements($theme);

    return implode("\n", array_map(static fn(string $line): string => $indent . $elements->fieldOptionDescription($line), Strings::wrap($description, $width)));
  }

  /**
   * The column an option's own text starts at, within the field's view.
   *
   * @param \DrevOps\PhpTui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return int
   *   The offset; zero for a field whose options carry no leading glyphs.
   */
  protected function optionTextOffset(ThemeInterface $theme): int {
    return 0;
  }

  /**
   * Offer a value as the answer, finishing the collection.
   *
   * The field does not validate: whatever holds the answer decides whether an
   * offered value stands. The offer always succeeds and clears any prior
   * error.
   *
   * @param mixed $value
   *   The collected value.
   */
  protected function accept(mixed $value): void {
    $this->error = NULL;
    $this->accepted = $value;
    $this->complete = TRUE;
  }

}
