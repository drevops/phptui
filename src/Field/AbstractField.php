<?php

declare(strict_types=1);

namespace DrevOps\Tui\Field;

use DrevOps\Tui\Block\Element\FieldElementsInterface;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Utils\Strings;

/**
 * Shared field behaviour: accept/cancel, validation and transformation.
 *
 * @package DrevOps\Tui\Field
 */
abstract class AbstractField implements FieldInterface {

  /**
   * The resolved key bindings for this field's scope.
   *
   * Injected by the field factory; when a field is constructed directly (for
   * a test or a one-off), it falls back to the default preset for its scope.
   */
  protected ?ScopedKeyMap $scoped = NULL;

  /**
   * The fuzzy matcher, created on first use.
   */
  protected ?Matcher $matcher = NULL;

  /**
   * Whether a valid value has been accepted.
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
   * The accepted, transformed value once complete.
   */
  protected mixed $accepted = NULL;

  /**
   * The validator `fn(mixed $value): ?string`, NULL accepting every value.
   *
   * Injected by the field factory via {@see setHandlers()}, like the key
   * bindings; a directly constructed field starts with neither.
   */
  protected ?\Closure $validate = NULL;

  /**
   * The transformer `fn(mixed $value): mixed` applied on accept, if any.
   */
  protected ?\Closure $transform = NULL;

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
   * {@inheritdoc}
   */
  public function setHandlers(?\Closure $validate = NULL, ?\Closure $transform = NULL): static {
    $this->validate = $validate;
    $this->transform = $transform;

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
   * Fields whose bindings differ from the base defaults override this; the
   * base scope is the right fallback for the rest.
   *
   * @return \DrevOps\Tui\Input\Scope
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
   * @param \DrevOps\Tui\Input\Key $key
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
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key triggered the accept - it is consumed whether or not
   *   the value passed validation.
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
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\Tui\Block\Element\FieldElementsInterface
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
   * Style one entry's label.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The entry label.
   * @param bool $current
   *   Whether the entry's row holds the cursor.
   * @param bool $chosen
   *   Whether the entry is picked.
   *
   * @return string
   *   The styled label.
   */
  protected function entryLabel(ThemeInterface $theme, string $label, bool $current, bool $chosen = FALSE): string {
    return $this->elements($theme)->fieldEntry($label, $chosen, $current);
  }

  /**
   * Render an exclusive entry row: the mark and the label beside it.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $label
   *   The entry label.
   * @param bool $current
   *   Whether the entry's row holds the cursor.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderExclusiveRow(ThemeInterface $theme, string $label, bool $current): string {
    // Moving the cursor is what picks in an exclusive list, so the mark and the
    // cursor say the same thing and the row draws only the mark.
    return $this->elements($theme)->fieldEntryMarker($current, TRUE) . ' ' . $this->entryLabel($theme, $label, $current);
  }

  /**
   * The shared fuzzy matcher.
   *
   * @return \DrevOps\Tui\Field\Matcher
   *   The matcher.
   */
  protected function matcher(): Matcher {
    return $this->matcher ??= new Matcher();
  }

  /**
   * Style an option label, emphasising the query-matched characters.
   *
   * The label is split into runs of matched and unmatched characters, each run
   * styled on its own so no SGR code nests inside another: matched runs get the
   * match colour, and the rest is drawn as the entry it belongs to. With no
   * matched positions this is exactly {@see entryLabel()}.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
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
      return $this->entryLabel($theme, $label, $current, $chosen);
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
   * Style one run of same-kind characters for {@see renderMatchedLabel()}.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
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
      return $this->elements($theme)->fieldEntryMatch($run);
    }

    return $this->entryLabel($theme, $run, $current, $chosen);
  }

  /**
   * {@inheritdoc}
   *
   * The frame every field shares, stacked so that each line sits nearest what
   * it belongs to: the field's own body, the highlighted option's detail
   * (choice fields only), then what the field expects of an answer, then why
   * the last one was refused. The detail leads because it changes as the
   * highlight moves - a line that follows the cursor belongs against the list
   * it follows, not below a constraint that never moves. A field renders only
   * its body via {@see renderBody()} and states its expectation via
   * {@see renderConstraint()}.
   */
  public function view(ThemeInterface $theme): string {
    $lines = [$this->renderBody($theme)];

    $detail = $this->renderOptionDescription($theme, $this->highlightedDescription());
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
   * What the field expects of an answer, before anything is refused.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
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
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The rendered body lines.
   */
  abstract protected function renderBody(ThemeInterface $theme): string;

  /**
   * The highlighted option's description; empty for fields without one.
   *
   * The choice fields override this (directly or via a capability trait) to
   * surface the highlighted option's description; every other field inherits
   * the empty default, so the shared frame adds no description line for it.
   *
   * @return string
   *   The description shown beneath the body, or an empty string.
   */
  protected function highlightedDescription(): string {
    return '';
  }

  /**
   * The narrowest content width at which an option description is still shown.
   *
   * Below this the panel is too narrow to render a readable description, so it
   * is dropped rather than wrapped into unreadable fragments.
   */
  protected const int MIN_DESCRIPTION_WIDTH = 8;

  /**
   * Render an option description, wrapped to the panel width and dimmed.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $description
   *   The description text.
   *
   * @return string
   *   The wrapped, dimmed line(s), or an empty string when there is no
   *   description or the panel is too narrow to show one.
   */
  protected function renderOptionDescription(ThemeInterface $theme, string $description): string {
    // Indented to start where an entry's own text starts, so it reads as
    // belonging to the entry above it rather than to the list as a whole.
    $indent = str_repeat(' ', $this->entryTextOffset($theme));
    $width = $theme->contentWidth() - Strings::length($indent);

    if ($description === '' || $width < self::MIN_DESCRIPTION_WIDTH) {
      return '';
    }

    $elements = $this->elements($theme);

    return implode("\n", array_map(static fn(string $line): string => $indent . $elements->fieldEntryDescription($line), Strings::wrap($description, $width)));
  }

  /**
   * The column an entry's own text starts at, within the field's view.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return int
   *   The offset; zero for a field whose entries carry no leading glyphs.
   */
  protected function entryTextOffset(ThemeInterface $theme): int {
    return 0;
  }

  /**
   * Validate and, when valid, transform a value and complete the field.
   *
   * @param mixed $value
   *   The candidate value.
   *
   * @return bool
   *   TRUE when the value was accepted; FALSE when validation failed.
   */
  protected function accept(mixed $value): bool {
    $error = $this->validate instanceof \Closure ? ($this->validate)($value) : NULL;
    if (is_string($error) && $error !== '') {
      $this->error = $error;

      return FALSE;
    }

    $this->error = NULL;
    $this->accepted = $this->transform instanceof \Closure ? ($this->transform)($value) : $value;
    $this->complete = TRUE;

    return TRUE;
  }

}
