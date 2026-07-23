<?php

declare(strict_types=1);

namespace DrevOps\Tui\Widget;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerMode;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Utils\Strings;
use DrevOps\Tui\Widget\Capability\FilterCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableInterface;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\RevealCapableInterface;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;

/**
 * A filesystem browser that selects a path, or several in multiple mode.
 *
 * Navigation walks directories from a start directory that also bounds the
 * browse - it is a floor the browser cannot ascend above. The mode governs
 * which entries may be selected (any, files or directories); directories stay
 * navigable regardless, so files beneath them remain reachable. Printable
 * characters filter the current directory; Tab reveals or hides dot-entries. In
 * multiple mode Space toggles the highlighted selectable entry and selections
 * accumulate across directories, so the value is the chosen path (single) or
 * the list of chosen paths (multiple).
 *
 * @package DrevOps\Tui\Widget
 */
class FilePickerWidget extends AbstractWidget implements FilterCapableInterface, RevealCapableInterface, PagingCapableInterface {

  use PagingCapableTrait;
  use SelectionBoundedTrait;

  /**
   * The start directory: where the browser opens and the floor it cannot pass.
   */
  protected string $root;

  /**
   * The directory currently being browsed.
   */
  protected string $cwd;

  /**
   * The selected paths as a set (path => TRUE), used in multiple mode.
   *
   * @var array<string,bool>
   */
  protected array $selected = [];

  /**
   * The normalized allowed extensions (dot-less, lowercase); empty allows all.
   *
   * @var list<string>
   */
  protected array $extensions;

  /**
   * The current type-to-filter text applied to the browsed directory.
   */
  protected string $filter = '';

  /**
   * The highlighted index within the visible entries.
   */
  protected int $cursor = 0;

  /**
   * Construct a file picker widget.
   *
   * @param string $start
   *   The start directory; the browser opens here and cannot ascend above it.
   *   Empty falls back to the current working directory.
   * @param string|list<string> $default
   *   The pre-selected path (single) or paths (multiple). A single path opens
   *   the browser at its directory with the entry highlighted; in multiple mode
   *   every path seeds the selection.
   * @param \DrevOps\Tui\Model\FilePickerMode $mode
   *   Which entries may be selected (any, files or directories).
   * @param list<string> $extensions
   *   The extensions selectable files are limited to (dot-less,
   *   case-insensitive); empty allows every extension.
   * @param bool $showHidden
   *   Whether dot-entries are shown when the browser opens.
   * @param bool $multiple
   *   Whether several paths may be selected (Space toggles, Enter accepts).
   * @param int|null $page_size
   *   The number of entry rows shown at once before the list pages; NULL uses
   *   the default.
   * @param \DrevOps\Tui\Model\SelectionBounds|null $selection_bounds
   *   The minimum/maximum selection counts enforced on accept, or NULL for no
   *   count limit.
   */
  public function __construct(
    string $start = '',
    string|array $default = '',
    protected FilePickerMode $mode = FilePickerMode::Any,
    array $extensions = [],
    protected bool $showHidden = FALSE,
    protected bool $multiple = FALSE,
    ?int $page_size = NULL,
    ?SelectionBounds $selection_bounds = NULL,
  ) {
    $this->root = $this->trimTrailingSlash($start !== '' ? $start : $this->currentDirectory());
    $this->cwd = $this->root;
    $this->pageSize = $this->resolvePageSize($page_size);
    $this->selectionBounds = $selection_bounds;

    $this->extensions = array_values(array_filter(array_map(
      static fn(string $extension): string => strtolower(ltrim($extension, '.')),
      $extensions,
    ), static fn(string $extension): bool => $extension !== ''));

    $this->seed($default);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function keyScope(): Scope {
    return Scope::field(FieldType::FilePicker, $this->multiple);
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Key $key): void {
    $keys = $this->keys();

    if ($this->handleCancel($key)) {
      return;
    }

    if ($keys->matches($key, Action::Accept)) {
      $this->onEnter();

      return;
    }

    if ($keys->matches($key, Action::MoveUp)) {
      $this->moveCursor(-1);

      return;
    }

    if ($keys->matches($key, Action::MoveDown)) {
      $this->moveCursor(1);

      return;
    }

    if ($keys->matches($key, Action::MoveRight)) {
      $this->descend();

      return;
    }

    if ($keys->matches($key, Action::MoveLeft)) {
      $this->ascend();

      return;
    }

    // Reveal doubles as the show-hidden toggle, mirroring the password reveal.
    if ($keys->matches($key, Action::Reveal)) {
      $this->toggleReveal();

      return;
    }

    if ($keys->matches($key, Action::Toggle)) {
      $this->toggleSelection();

      return;
    }

    if ($keys->matches($key, Action::DeleteBack)) {
      $this->onBackspace();

      return;
    }

    if ($key->isChar()) {
      $this->filter .= $key->char ?? '';
      $this->resetFilterCursor();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filter(): string {
    return $this->filter;
  }

  /**
   * {@inheritdoc}
   */
  public function resetFilterCursor(): void {
    $this->cursor = 0;
    $this->offset = 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function liveValue(): mixed {
    if ($this->multiple) {
      return array_keys($this->selected);
    }

    $name = $this->currentName();

    return $name === '' ? '' : $this->join($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function renderBody(ThemeInterface $theme): string {
    $lines = [$theme->breadcrumb($this->crumb())];

    $hint = $this->selectionHint($theme);
    if ($hint !== '') {
      $lines[] = $hint;
    }

    if ($this->filter !== '') {
      $lines[] = $this->filter . $theme->caret();
    }

    $entries = $this->entries();

    if ($entries === []) {
      $lines[] = $theme->description(Translator::t('(empty)'));
    }

    $viewport = $this->pageViewport(count($entries), $this->cursor);

    $rows = [];

    foreach (array_slice($entries, $viewport->offset, $this->pageSize) as $slot => $name) {
      $rows[] = $this->renderRow($theme, $name, $viewport->offset + $slot === $this->cursor);
    }

    return implode("\n", array_merge($lines, $this->wrapScrolled($theme, $rows, $viewport)));
  }

  /**
   * {@inheritdoc}
   *
   * The Toggle fragment resolves only in multiple mode, where Space is bound to
   * it; Accept reads "select" for a single pick and "accept" for multiple.
   */
  #[\Override]
  public function hints(): array {
    return [
      new Hint('select', Action::Toggle),
      new Hint('move', Action::MoveUp, Action::MoveDown),
      new Hint('open', Action::MoveRight),
      new Hint('up', Action::MoveLeft),
      new Hint($this->multiple ? 'accept' : 'select', Action::Accept),
      new Hint('hidden', Action::Reveal),
      new Hint('cancel', Action::Cancel),
    ];
  }

  /**
   * Seed the initial selection and browse location from the default.
   *
   * @param string|list<string> $default
   *   The default path or paths.
   */
  protected function seed(string|array $default): void {
    $paths = is_array($default) ? Field::stringList($default) : ($default === '' ? [] : [$default]);

    if ($this->multiple) {
      foreach ($paths as $path) {
        if ($path !== '') {
          $this->selected[$path] = TRUE;
        }
      }
    }

    $primary = $paths[0] ?? '';
    if ($primary === '' || !str_starts_with($primary, $this->root . '/')) {
      return;
    }

    $this->cwd = $this->parentOf($primary);
    $this->highlight($this->baseName($primary));
  }

  /**
   * Accept the highlighted entry, the accumulated selection, or descend.
   */
  protected function onEnter(): void {
    if ($this->multiple) {
      $this->accept($this->liveValue());

      return;
    }

    $name = $this->currentName();
    if ($name === '') {
      return;
    }

    if ($this->isSelectable($name)) {
      $this->accept($this->join($name));

      return;
    }

    if ($this->isDir($name)) {
      $this->descend();
    }
  }

  /**
   * The directory the browser roots at when no start directory is declared.
   *
   * A seam so the fallback can come from somewhere other than the process
   * working directory (e.g. a virtual filesystem).
   *
   * @return string
   *   The current working directory.
   */
  protected function currentDirectory(): string {
    // @codeCoverageIgnoreStart
    return (string) getcwd();
    // @codeCoverageIgnoreEnd
  }

  /**
   * Delete the last filter character, or ascend when the filter is empty.
   */
  protected function onBackspace(): void {
    if ($this->filter !== '') {
      $this->filter = Strings::substr($this->filter, 0, -1);
      $this->resetFilterCursor();

      return;
    }

    $this->ascend();
  }

  /**
   * Move the highlight by a delta, clamped to the visible entries.
   *
   * @param int $delta
   *   The direction (negative up, positive down).
   */
  protected function moveCursor(int $delta): void {
    $count = count($this->entries());
    if ($count === 0) {
      $this->cursor = 0;

      return;
    }

    $this->cursor = max(0, min($count - 1, $this->cursor + $delta));
  }

  /**
   * Descend into the highlighted directory.
   */
  protected function descend(): void {
    $name = $this->currentName();
    if ($name === '' || !$this->isDir($name)) {
      return;
    }

    $this->cwd = $this->join($name);
    $this->resetView();
  }

  /**
   * Ascend to the parent directory, never above the start directory.
   */
  protected function ascend(): void {
    if ($this->cwd === $this->root) {
      return;
    }

    $left = $this->baseName($this->cwd);
    $this->cwd = $this->parentOf($this->cwd);
    $this->resetView();
    $this->highlight($left);
  }

  /**
   * {@inheritdoc}
   *
   * Toggles whether dot-entries are shown, landing back at the top of the
   * refreshed listing.
   */
  public function toggleReveal(): void {
    $this->showHidden = !$this->showHidden;
    $this->cursor = 0;
    $this->offset = 0;
  }

  /**
   * Toggle the highlighted entry in the selection, when it is selectable.
   */
  protected function toggleSelection(): void {
    $name = $this->currentName();
    if ($name === '' || !$this->isSelectable($name)) {
      return;
    }

    $path = $this->join($name);
    if (isset($this->selected[$path])) {
      unset($this->selected[$path]);

      return;
    }

    $this->selected[$path] = TRUE;
  }

  /**
   * Reset the filter, highlight and scroll after changing directory.
   */
  protected function resetView(): void {
    $this->filter = '';
    $this->cursor = 0;
    $this->offset = 0;
  }

  /**
   * Move the highlight to a named entry, or the top when it is not visible.
   *
   * @param string $name
   *   The entry name.
   */
  protected function highlight(string $name): void {
    $index = array_search($name, $this->entries(), TRUE);
    $this->cursor = $index === FALSE ? 0 : $index;
  }

  /**
   * The visible entry names in the browsed directory, directories first.
   *
   * @return list<string>
   *   The entry names, sorted case-insensitively with directories before files.
   */
  protected function entries(): array {
    if (!is_dir($this->cwd)) {
      return [];
    }

    $raw = scandir($this->cwd);
    // @codeCoverageIgnoreStart
    if ($raw === FALSE) {
      return [];
    }
    // @codeCoverageIgnoreEnd
    $dirs = [];
    $files = [];
    foreach ($raw as $name) {
      if ($name === '.') {
        continue;
      }
      if ($name === '..') {
        continue;
      }
      if (!$this->showHidden && str_starts_with($name, '.')) {
        continue;
      }

      if (is_dir($this->cwd . '/' . $name)) {
        $dirs[] = $name;

        continue;
      }
      if ($this->mode === FilePickerMode::Directory) {
        continue;
      }
      if (!$this->extensionAllowed($name)) {
        continue;
      }

      $files[] = $name;
    }

    return array_merge($this->sortFilter($dirs), $this->sortFilter($files));
  }

  /**
   * Apply the type-to-filter query and case-insensitive sort to a name list.
   *
   * @param list<string> $names
   *   The entry names.
   *
   * @return list<string>
   *   The filtered, sorted names.
   */
  protected function sortFilter(array $names): array {
    if ($this->filter !== '') {
      $needle = strtolower($this->filter);
      $names = array_filter($names, static fn(string $name): bool => str_contains(strtolower($name), $needle));
    }

    usort($names, strcasecmp(...));

    return $names;
  }

  /**
   * The highlighted entry name, or an empty string when there is none.
   *
   * @return string
   *   The entry name.
   */
  protected function currentName(): string {
    $entries = $this->entries();

    return $entries[$this->cursor] ?? '';
  }

  /**
   * Whether an entry may be selected under the current mode.
   *
   * @param string $name
   *   The entry name.
   *
   * @return bool
   *   TRUE when the entry is selectable.
   */
  protected function isSelectable(string $name): bool {
    return match ($this->mode) {
      FilePickerMode::Any => TRUE,
      FilePickerMode::File => !$this->isDir($name),
      FilePickerMode::Directory => $this->isDir($name),
    };
  }

  /**
   * Whether a filename's extension is allowed.
   *
   * @param string $name
   *   The entry name.
   *
   * @return bool
   *   TRUE when the extension is allowed (or no restriction applies).
   */
  protected function extensionAllowed(string $name): bool {
    if ($this->extensions === []) {
      return TRUE;
    }

    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $this->extensions, TRUE);
  }

  /**
   * Whether a browsed-directory entry is itself a directory.
   *
   * @param string $name
   *   The entry name.
   *
   * @return bool
   *   TRUE when the entry is a directory.
   */
  protected function isDir(string $name): bool {
    return is_dir($this->join($name));
  }

  /**
   * Render a single entry row.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   * @param string $name
   *   The entry name.
   * @param bool $current
   *   Whether the row holds the highlight.
   *
   * @return string
   *   The rendered row.
   */
  protected function renderRow(ThemeInterface $theme, string $name, bool $current): string {
    $label = $this->isDir($name) ? $name . '/' : $name;
    $row = $theme->marker($current) . ' ';

    if ($this->multiple) {
      $box = $this->isSelectable($name) ? $theme->check(isset($this->selected[$this->join($name)])) : $this->blankBox($theme);
      $row .= $box . ' ';
    }

    return $row . $this->highlightLabel($theme, $label, $current);
  }

  /**
   * A spacer the width of a checkbox, for entries that cannot be selected.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return string
   *   The spacer.
   */
  protected function blankBox(ThemeInterface $theme): string {
    return str_repeat(' ', Strings::length(Ansi::strip($theme->check(FALSE))));
  }

  /**
   * The breadcrumb of the browsed directory, relative to the start directory.
   *
   * @return string
   *   The breadcrumb.
   */
  protected function crumb(): string {
    $base = $this->baseName($this->root);
    if ($base === '') {
      $base = $this->root;
    }

    return $base . substr($this->cwd, strlen($this->root));
  }

  /**
   * Join an entry name onto the browsed directory.
   *
   * @param string $name
   *   The entry name.
   *
   * @return string
   *   The full path.
   */
  protected function join(string $name): string {
    return $this->cwd === '/' ? '/' . $name : $this->cwd . '/' . $name;
  }

  /**
   * The parent of a path, never shorter than the start directory.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The parent path, clamped to the start directory.
   */
  protected function parentOf(string $path): string {
    $pos = strrpos($path, '/');
    // @codeCoverageIgnoreStart
    if ($pos === FALSE) {
      return $this->root;
    }
    // @codeCoverageIgnoreEnd
    $parent = $pos === 0 ? '/' : substr($path, 0, $pos);

    return strlen($parent) < strlen($this->root) ? $this->root : $parent;
  }

  /**
   * The last segment of a path.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The last segment.
   */
  protected function baseName(string $path): string {
    $pos = strrpos($path, '/');

    return $pos === FALSE ? $path : substr($path, $pos + 1);
  }

  /**
   * Trim a trailing slash, keeping the filesystem root itself.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The trimmed path.
   */
  protected function trimTrailingSlash(string $path): string {
    $trimmed = rtrim($path, '/');

    return $trimmed === '' ? '/' : $trimmed;
  }

}
