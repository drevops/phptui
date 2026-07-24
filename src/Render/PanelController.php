<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\Input\ScopedKeyMap;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Widget\Capability\ExternalEditCapableInterface;
use DrevOps\Tui\Widget\WidgetFactory;
use DrevOps\Tui\Widget\WidgetInterface;

/**
 * The interactive state machine behind the panel TUI.
 *
 * Holds navigation, the selection cursor, the scroll offset and the current
 * editor, and advances on one key at a time - so the whole interaction is
 * testable headlessly. Mouse-wheel scrolls without moving the cursor; a key
 * press re-engages cursor-follow. Editing a field returns to the panel with
 * the new value shown and marked "edited" (or "override" when it pins a derive
 * rule), and every accepted edit re-settles the form logic - derive rules
 * recompute, `when` conditions show and hide fields, fix-ups re-apply - so the
 * session honours the same behaviour a headless collection does.
 *
 * @package DrevOps\Tui\Render
 *
 * @internal
 *   Consumers drive the TUI through the {@see \DrevOps\Tui\Tui} facade (or
 *   {@see \DrevOps\Tui\Testing\TuiTester} in tests), not this class.
 */
class PanelController {

  /**
   * The submit/cancel button pair appended after a root panel's items.
   */
  protected const int BUTTON_COUNT = 2;

  /**
   * The cancel button's index within the button pair.
   */
  protected const int CANCEL_BUTTON = 1;

  /**
   * The panel navigator.
   */
  protected Navigator $navigator;

  /**
   * The selection cursor within the current panel.
   */
  protected int $cursor = 0;

  /**
   * The scroll offset of the current panel.
   */
  protected int $offset = 0;

  /**
   * Whether the viewport follows the cursor (a key press re-engages it).
   */
  protected bool $followCursor = TRUE;

  /**
   * The active field editor, if any.
   */
  protected ?WidgetInterface $editor = NULL;

  /**
   * The field being edited, if any.
   */
  protected ?Field $editing = NULL;

  /**
   * The widget factory.
   */
  protected WidgetFactory $widgets;

  /**
   * The external-editor service.
   */
  protected ExternalEditor $externalEditor;

  /**
   * The terminal, set while the interactive loop is running.
   */
  protected ?Terminal $terminal = NULL;

  /**
   * The resolved key bindings.
   */
  protected KeyMap $keymap;

  /**
   * The navigation-scope key bindings.
   */
  protected ScopedKeyMap $nav;

  /**
   * The scroller.
   */
  protected Scroller $scroller;

  /**
   * Whether the user has chosen to quit.
   */
  protected bool $done = FALSE;

  /**
   * Whether the user cancelled via the cancel button.
   */
  protected bool $cancelled = FALSE;

  /**
   * Whether the user aborted the loop with the interrupt key (Ctrl-C).
   */
  protected bool $interrupted = FALSE;

  /**
   * Whether the help overlay is showing.
   */
  protected bool $help = FALSE;

  /**
   * The answer values snapshot taken when the current modal dialog opened.
   *
   * @var array<string,mixed>
   */
  protected array $modalValues = [];

  /**
   * The provenance snapshot taken when the current modal dialog opened.
   *
   * @var array<string,\DrevOps\Tui\Answers\Provenance>
   */
  protected array $modalProvenance = [];

  /**
   * The cursor position to restore in the parent when a modal dialog closes.
   */
  protected int $modalReturnCursor = 0;

  /**
   * The scroll offset to restore in the parent when a modal dialog closes.
   */
  protected int $modalReturnOffset = 0;

  /**
   * The resolved fullscreen minimum width, measured lazily from the content.
   */
  protected ?int $minWidth = NULL;

  /**
   * The engine settling derives, activation and fix-ups after each edit.
   */
  protected Engine $engine;

  /**
   * The conditional-activation map keyed by field id.
   *
   * @var array<string,bool>
   */
  protected array $active = [];

  /**
   * Construct a controller.
   *
   * The order groups the arguments by role - the form and its theme, the
   * answer state, the collaborating services, then the display chrome - so a
   * caller reaches the common ones positionally and names the rest.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The form definition (panels, fields, titles and submit/cancel chrome).
   * @param \DrevOps\Tui\Theme\DefaultTheme $theme
   *   The theme (the visual authority for rendering).
   * @param array<string,mixed> $values
   *   The initial answer values (typically the engine's resolved answers).
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The initial provenance.
   * @param \DrevOps\Tui\Input\KeyMap|null $keymap
   *   The resolved key bindings; NULL uses the default preset.
   * @param \DrevOps\Tui\Handler\HandlerRegistry|null $handlers
   *   The registry resolving a field id to its reusable static
   *   validate()/transform() behaviour; NULL leaves only the declared closures.
   * @param \DrevOps\Tui\Render\ExternalEditor|null $external_editor
   *   The external-editor service (defaults to a real one); injectable for
   *   tests and to gate the textarea handoff on editor availability.
   * @param bool $footer
   *   Whether the contextual key-hint footer is shown.
   * @param bool $clearOnExit
   *   Whether to clear the screen when the interactive loop exits.
   * @param string $banner
   *   An optional start banner (logo) shown before the interactive loop.
   * @param string $version
   *   An optional version string shown below the banner.
   */
  public function __construct(
    protected FormDefinition $form,
    protected DefaultTheme $theme,
    protected array $values = [],
    protected array $provenance = [],
    ?KeyMap $keymap = NULL,
    ?HandlerRegistry $handlers = NULL,
    ?ExternalEditor $external_editor = NULL,
    protected bool $footer = TRUE,
    protected bool $clearOnExit = TRUE,
    protected string $banner = '',
    protected string $version = '',
  ) {
    $this->keymap = $keymap ?? KeyMapManager::create();
    $this->externalEditor = $external_editor ?? new ExternalEditor();
    $this->widgets = new WidgetFactory($this->keymap, $this->externalEditor->isAvailable(), $handlers);
    $this->nav = $this->keymap->navigation();
    $this->scroller = new Scroller();
    $this->navigator = new Navigator(new Panel('hub', $form->title, '', panels: $form->panels, layout: $form->layout));
    $this->engine = new Engine($form, $handlers ?? new HandlerRegistry());

    // Settle once at construction so the activation map exists before the
    // first frame and a seeded value set is coherent with the form logic.
    $this->resettle();
  }

  /**
   * Process one key press.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  public function handle(Key $key): void {
    if ($this->help) {
      // Any key dismisses the help overlay.
      $this->help = FALSE;

      return;
    }

    if ($this->editor instanceof WidgetInterface) {
      $this->handleEditing($key);

      return;
    }

    $this->handleNavigation($key);
  }

  /**
   * Whether a field is being edited.
   *
   * @return bool
   *   TRUE when editing.
   */
  public function isEditing(): bool {
    return $this->editor instanceof WidgetInterface;
  }

  /**
   * Whether the user has chosen to quit.
   *
   * @return bool
   *   TRUE when done.
   */
  public function isDone(): bool {
    return $this->done;
  }

  /**
   * Whether the user cancelled.
   *
   * @return bool
   *   TRUE when the user activated the cancel button.
   */
  public function isCancelled(): bool {
    return $this->cancelled;
  }

  /**
   * Whether the user aborted with the interrupt key (Ctrl-C).
   *
   * @return bool
   *   TRUE when the loop ended on an interrupt.
   */
  public function isInterrupted(): bool {
    return $this->interrupted;
  }

  /**
   * Whether the help overlay is showing.
   *
   * @return bool
   *   TRUE when the overlay is open.
   */
  public function isShowingHelp(): bool {
    return $this->help;
  }

  /**
   * Run the interactive loop against a terminal until the user quits or aborts.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   */
  public function run(Terminal $terminal): Answers {
    $parser = new KeyParser();
    $this->terminal = $terminal;
    $terminal->setup($this->theme->background());
    $this->resolveLoaders($this->navigator->current());

    try {
      if ($this->banner !== '') {
        $terminal->render($this->positioned($this->theme->renderBanner($this->banner, $this->version) . "\n\n" . Translator::t('Press any key to continue...'), $terminal));

        // Any key dismisses the banner, but Ctrl-C here aborts like it does
        // mid-form rather than dropping the user into the questionnaire.
        foreach ($parser->parse($terminal->read()) as $key) {
          if ($this->consumeInterrupt($key)) {
            break;
          }
        }
      }

      while (!$this->done && !$this->interrupted) {
        $too_small = $this->tooSmall($terminal);
        $terminal->render($too_small ? $this->tooSmallFrame($terminal) : $this->positioned($this->frame($this->rows($terminal)), $terminal));

        $bytes = $terminal->read();

        // An empty read means the input is exhausted - the scripted input ran
        // out or the stream closed. Stop rather than spin re-rendering forever.
        if ($bytes === '') {
          break;
        }

        foreach ($parser->parse($bytes) as $key) {
          // Ctrl-C aborts from anywhere - including mid-widget - so catch it
          // above handle() and drop straight out of the read loop to the
          // teardown, leaving the collected answers as they stand.
          if ($this->consumeInterrupt($key)) {
            break 2;
          }

          // The too-small guard screen accepts only quit: any other key would
          // mutate state invisibly behind the notice. Quit routes through the
          // normal navigation handling so an open modal is dismissed (and its
          // snapshot restored) rather than the whole form ending over it.
          if ($too_small) {
            if ($this->nav->matches($key, Action::Quit)) {
              $this->handleNavigation($key);
            }

            continue;
          }

          $this->handle($key);
        }
      }
    }
    finally {
      $terminal->restore();
      // An interrupt always leaves a clean screen, even when a consumer opted
      // out of the clear-on-exit for a normal finish.
      if ($this->clearOnExit || $this->interrupted) {
        $terminal->clear();
      }
    }

    return $this->answers();
  }

  /**
   * Record an abort when the key is the interrupt (Ctrl-C).
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key to test.
   *
   * @return bool
   *   TRUE when the key was the interrupt, so the caller stops reading input.
   */
  protected function consumeInterrupt(Key $key): bool {
    if (!$key->is(KeyName::Interrupt)) {
      return FALSE;
    }

    $this->interrupted = TRUE;

    return TRUE;
  }

  /**
   * The selection cursor.
   *
   * @return int
   *   The cursor index.
   */
  public function cursor(): int {
    return $this->cursor;
  }

  /**
   * The current panel.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The current panel.
   */
  public function currentPanel(): Panel {
    return $this->navigator->current();
  }

  /**
   * The current answers: the active fields' values and provenance.
   *
   * Inactive (condition-failing) fields keep their settled values internally -
   * so a later activation change surfaces them intact - but contribute no
   * answer, matching what a headless collection returns.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The self-describing answers.
   */
  public function answers(): Answers {
    $values = [];
    $provenance = [];

    foreach ($this->form->fields() as $field) {
      if (!($this->active[$field->id] ?? TRUE)) {
        continue;
      }

      if (array_key_exists($field->id, $this->values)) {
        $values[$field->id] = $this->values[$field->id];
      }

      if (isset($this->provenance[$field->id])) {
        $provenance[$field->id] = $this->provenance[$field->id];
      }
    }

    return Answers::forForm($this->form, $values, $provenance);
  }

  /**
   * Render the current frame: the help overlay, the editor or the panel hub.
   *
   * @param int $rows
   *   The screen rows the frame may fill.
   *
   * @return string
   *   The frame.
   */
  public function frame(int $rows): string {
    if ($this->help) {
      return $this->theme->renderHelp($this->nav, ...$this->helpSections());
    }

    // A standalone field takes the whole screen; an inline field expands inside
    // the hub, which hubFrame() splices in.
    if ($this->editor instanceof WidgetInterface && $this->editing instanceof Field && $this->editing->render === RenderMode::Standalone) {
      return $this->editorFrame($this->editor, $rows);
    }

    if ($this->navigator->current()->isModal()) {
      return $this->modalFrame($rows);
    }

    return $this->hubFrame($rows);
  }

  /**
   * Render the editor screen for the field being edited.
   *
   * @param \DrevOps\Tui\Widget\WidgetInterface $editor
   *   The active editor widget.
   * @param int $rows
   *   The screen rows a fullscreen editor stretches to.
   *
   * @return string
   *   The editor frame.
   */
  protected function editorFrame(WidgetInterface $editor, int $rows): string {
    $label = $this->editing instanceof Field ? Translator::t($this->editing->label) : '';
    $keys = $this->editing instanceof Field ? $this->keymap->forField($this->editing->type) : $this->nav;
    $hints = $this->footer ? $editor->hints() : [];

    return $this->theme->renderEditor($label, $editor->view($this->theme), $hints, $keys, $rows);
  }

  /**
   * Render the panel hub: the body with buttons, scrolled, framed by chrome.
   *
   * @param int $rows
   *   The screen rows the frame may fill.
   *
   * @return string
   *   The hub frame.
   */
  protected function hubFrame(int $rows): string {
    $panel = $this->viewPanel($this->navigator->current());

    // When an inline field is being edited, hand its field and rendered view to
    // the body so the theme expands the editor in place of the summary row.
    $editing = NULL;
    $view = '';
    if ($this->editor instanceof WidgetInterface && $this->editing instanceof Field) {
      $editing = $this->editing;
      $view = $this->editor->view($this->theme);
    }

    [$body, $cursor_line] = $this->theme->renderBody($panel, $this->answers(), $this->cursor, $editing, $view);

    if ($this->buttonsVisible()) {
      $base = $panel->itemCount();
      $selected = $this->cursor >= $base ? $this->cursor - $base : -1;

      // The action row always detaches from the items above it.
      $body[] = '';

      if ($this->cursor >= $base) {
        $cursor_line = count($body);
      }

      $body[] = $this->theme->renderButtonBar([
        Translator::t($this->form->buttons->submitLabel),
        Translator::t($this->form->buttons->cancelLabel),
      ], $selected);
    }

    $header = [$this->theme->renderBreadcrumbLine($this->navigator)];
    $footer = $editing instanceof Field ? $this->inlineEditFooter() : $this->hubFooter();
    $height = $this->viewportHeight($rows, count($header), count($footer));
    $viewport = $this->resolveViewport(count($body), $cursor_line, $height);

    return $this->theme->renderFrame($header, $body, $footer, $viewport, $height);
  }

  /**
   * The body viewport height that fits a frame into the screen rows.
   *
   * The theme owns the chrome accounting, so a bordered or padded frame never
   * overflows the terminal.
   *
   * @param int $rows
   *   The screen rows the frame may fill.
   * @param int $header_lines
   *   The header line count.
   * @param int $footer_lines
   *   The footer line count.
   *
   * @return int
   *   The viewport height, at least 3.
   */
  protected function viewportHeight(int $rows, int $header_lines, int $footer_lines): int {
    return max(3, $rows - $header_lines - $footer_lines - $this->theme->chromeHeight($footer_lines > 0));
  }

  /**
   * The screen rows a frame may fill: the terminal's, capped by the theme.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return int
   *   The row budget.
   */
  protected function rows(Terminal $terminal): int {
    $max = $this->theme->maxHeight();
    $rows = $terminal->height();

    return $max > 0 ? min($rows, $max) : $rows;
  }

  /**
   * Render the current modal dialog floating over its dimmed parent.
   *
   * @param int $rows
   *   The screen rows the frame may fill.
   *
   * @return string
   *   The modal frame.
   */
  protected function modalFrame(int $rows): string {
    $modal = $this->viewPanel($this->navigator->current());

    $editing = NULL;
    $view = '';
    if ($this->editor instanceof WidgetInterface && $this->editing instanceof Field) {
      $editing = $this->editing;
      $view = $this->editor->view($this->theme);
    }

    $base = $modal->itemCount();
    $selected = $this->cursor >= $base ? $this->cursor - $base : -1;

    // The dialog floats over the whole backdrop frame, so the screen rows -
    // not the body viewport - bound it; the theme deducts the dialog's own
    // chrome from that budget itself.
    return $this->theme->renderModal($modal, $this->answers(), $this->cursor, $editing, $view, $selected, $this->backdrop($rows), $rows);
  }

  /**
   * Render the parent panel as the backdrop a modal dialog floats over.
   *
   * The parent renders un-highlighted; the theme dims it while compositing the
   * dialog on top, so what shows through the padding reads as recessed.
   *
   * @param int $rows
   *   The screen rows the frame may fill.
   *
   * @return string
   *   The parent frame.
   */
  protected function backdrop(int $rows): string {
    $parent = $this->navigator->parent();

    if (!$parent instanceof Panel) {
      // A modal is always entered from a parent, so this never happens.
      // @codeCoverageIgnoreStart
      $parent = $this->navigator->current();
      // @codeCoverageIgnoreEnd
    }

    [$body] = $this->theme->renderBody($this->viewPanel($parent), $this->answers(), -1);
    $header = [$this->theme->renderBreadcrumbLine($this->navigator)];
    $footer = $this->hubFooter();
    $height = $this->viewportHeight($rows, count($header), count($footer));
    $viewport = $this->scroller->viewport(0, count($body), $height);

    return $this->theme->renderFrame($header, $body, $footer, $viewport, $height);
  }

  /**
   * Position a frame within the terminal area per the layout options.
   *
   * Outside fullscreen the frame renders where the cursor homes, as always.
   * In fullscreen a frame smaller than the terminal - a capped hub, an
   * unboxed editor, the help overlay, the banner - anchors to the alignment
   * the theme options pick, padded with blank space.
   *
   * @param string $frame
   *   The rendered frame.
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return string
   *   The positioned frame.
   */
  protected function positioned(string $frame, Terminal $terminal): string {
    if (!$this->theme->isFullscreen()) {
      return $frame;
    }

    $lines = explode("\n", $frame);
    $area_width = $terminal->width();
    $area_height = $terminal->height();
    $box_width = Ansi::blockWidth($lines);

    if (count($lines) >= $area_height && $box_width >= $area_width) {
      return $frame;
    }

    [$top, $left] = Overlay::place($area_width, $area_height, $box_width, count($lines), $this->theme->halign(), $this->theme->valign());
    $backdrop = array_fill(0, $area_height, str_repeat(' ', $area_width));

    return implode("\n", Overlay::composite($backdrop, $lines, $box_width, $top, $left));
  }

  /**
   * Whether the terminal is too small for the fullscreen layout.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return bool
   *   TRUE when fullscreen is on and the terminal is below the minimums.
   */
  protected function tooSmall(Terminal $terminal): bool {
    if (!$this->theme->isFullscreen()) {
      return FALSE;
    }
    if ($terminal->width() < $this->minWidth()) {
      return TRUE;
    }
    return $terminal->height() < $this->minHeight();
  }

  /**
   * The effective fullscreen minimum width.
   *
   * An explicit "min_width" option wins; otherwise the content is measured
   * once, at the initial answers, so the guard never flaps as values grow
   * mid-session. A "max_width" cap bounds the result: the cap is the
   * consumer's word that clipping is acceptable, and a guard demanding more
   * than the cap allows could never be satisfied by resizing.
   *
   * @return int
   *   The minimum width, in columns.
   */
  protected function minWidth(): int {
    if ($this->minWidth === NULL) {
      $min = $this->theme->minWidth() > 0 ? $this->theme->minWidth() : $this->theme->measureContentWidth($this->form, $this->answers());
      $max = $this->theme->maxWidth();
      $this->minWidth = $max > 0 ? min($min, $max) : $min;
    }

    return $this->minWidth;
  }

  /**
   * The effective fullscreen minimum height, bounded like the minimum width.
   *
   * @return int
   *   The minimum height, in rows.
   */
  protected function minHeight(): int {
    $max = $this->theme->maxHeight();
    $min = $this->theme->minHeight();

    return $max > 0 ? min($min, $max) : $min;
  }

  /**
   * Render the centered notice shown while the terminal is too small.
   *
   * Always centered - the alignment options position content on a screen the
   * layout fits into, which this one is not.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return string
   *   The notice screen.
   */
  protected function tooSmallFrame(Terminal $terminal): string {
    $lines = [
      $this->theme->error(Translator::t('Terminal too small.')),
      Translator::t('Need at least @width x @height - have @w x @h.', [
        '@width' => (string) $this->minWidth(),
        '@height' => (string) $this->minHeight(),
        '@w' => (string) $terminal->width(),
        '@h' => (string) $terminal->height(),
      ]),
      $this->theme->renderHints($this->nav, new Hint('quit', Action::Quit)),
    ];

    $width = Ansi::blockWidth($lines);

    [$top, $left] = Overlay::center($terminal->width(), $terminal->height(), $width, count($lines));
    $backdrop = array_fill(0, max(count($lines), $terminal->height()), str_repeat(' ', max($width, $terminal->width())));

    return implode("\n", Overlay::composite($backdrop, $lines, $width, $top, $left));
  }

  /**
   * Resolve and persist the scroll viewport for the hub body.
   *
   * Follows the cursor unless wheel scrolling has detached it; the resolved
   * offset persists so the next frame scrolls from where this one settled.
   *
   * @param int $total
   *   The total number of body lines.
   * @param int $cursor_line
   *   The line index of the selected item.
   * @param int $height
   *   The body viewport height.
   *
   * @return \DrevOps\Tui\Render\Viewport
   *   The resolved viewport.
   */
  protected function resolveViewport(int $total, int $cursor_line, int $height): Viewport {
    $viewport = $this->followCursor ? $this->scroller->follow($total, $height, $cursor_line, $this->offset) : $this->scroller->viewport($this->offset, $total, $height);
    $this->offset = $viewport->offset;

    return $viewport;
  }

  /**
   * Handle a key while editing a field.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  protected function handleEditing(Key $key): void {
    if (!$this->editor instanceof WidgetInterface || !$this->editing instanceof Field) {
      // @codeCoverageIgnoreStart
      return;
      // @codeCoverageIgnoreEnd
    }

    $this->editor->handle($key);

    if ($this->editor instanceof ExternalEditCapableInterface && $this->editor->wantsExternalEdit()) {
      $current = $this->editor->value();
      $captured = $this->externalEditor->edit(is_string($current) ? $current : '', $this->terminal);
      $this->editor->applyExternalEdit($captured);
    }

    if ($this->editor->isComplete()) {
      $this->values[$this->editing->id] = $this->editor->value();
      // Editing a derive-ruled field pins its rule, exactly as a headless
      // input to it would, so both paths badge the same situation the same.
      $this->provenance[$this->editing->id] = $this->editing->derive instanceof Derive ? Provenance::Override : Provenance::Edited;
      $this->closeEditor();
      $this->resettle();
    }
    elseif ($this->editor->isCancelled()) {
      $this->closeEditor();
    }
  }

  /**
   * Re-settle the form logic over the current values and clamp the cursor.
   *
   * Runs the engine's settling - derive rules, conditional activation and
   * fix-ups - so an interactive change propagates exactly as a headless input
   * does, then keeps the cursor inside the possibly-changed item range.
   */
  protected function resettle(): void {
    [$this->active, $this->values] = $this->engine->settle($this->values, $this->pinnedDerives());

    $count = $this->itemCountFor($this->navigator->current()) + ($this->buttonsVisible() ? self::BUTTON_COUNT : 0);
    $this->cursor = max(0, min(max(0, $count - 1), $this->cursor));
  }

  /**
   * The derive-ruled fields whose values are pinned against recomputation.
   *
   * Mirrors the headless pinning rule: a derive target the user supplied
   * (override) or discovery detected keeps its value, and every other derive
   * target follows its rule.
   *
   * @return array<string,bool>
   *   The pinned map keyed by field id.
   */
  protected function pinnedDerives(): array {
    $pinned = [];

    foreach ($this->form->fields() as $field) {
      if ($field->derive !== NULL) {
        $provenance = $this->provenance[$field->id] ?? Provenance::Default;
        $pinned[$field->id] = $provenance === Provenance::Override || $provenance === Provenance::Detected;
      }
    }

    return $pinned;
  }

  /**
   * A panel's fields whose conditions currently hold, in declaration order.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel.
   *
   * @return list<\DrevOps\Tui\Model\Field>
   *   The active fields.
   */
  protected function visibleFields(Panel $panel): array {
    return array_values(array_filter($panel->fields, fn(Field $field): bool => $this->active[$field->id] ?? TRUE));
  }

  /**
   * The number of navigable items on a panel: active fields plus sub-panels.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel.
   *
   * @return int
   *   The item count.
   */
  protected function itemCountFor(Panel $panel): int {
    return count($this->visibleFields($panel)) + count($panel->panels);
  }

  /**
   * A rendering copy of a panel holding only its active fields, recursively.
   *
   * Built fresh per frame so activation changes surface immediately.
   * Navigation keeps the original panel objects - only what the theme draws is
   * filtered - and the copy carries the original Field and sub-panel content,
   * so the filtered tree stays in lock-step with the real one.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel.
   *
   * @return \DrevOps\Tui\Model\Panel
   *   The filtered copy.
   */
  protected function viewPanel(Panel $panel): Panel {
    return new Panel($panel->id, $panel->title, $panel->description, $this->visibleFields($panel), array_map($this->viewPanel(...), $panel->panels), $panel->modal, $panel->layout);
  }

  /**
   * Handle a key while navigating a panel.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  protected function handleNavigation(Key $key): void {
    if ($this->nav->matches($key, Action::Help)) {
      $this->help = TRUE;

      return;
    }

    if ($this->nav->matches($key, Action::Quit)) {
      // A modal is blocking: quit dismisses the dialog, not the whole form.
      if ($this->navigator->current()->isModal()) {
        $this->closeModal(TRUE);
      }
      else {
        $this->done = TRUE;
      }

      return;
    }

    if ($this->nav->matches($key, Action::ScrollUp)) {
      $this->offset = max(0, $this->offset - 1);
      $this->followCursor = FALSE;

      return;
    }

    if ($this->nav->matches($key, Action::ScrollDown)) {
      $this->offset++;
      $this->followCursor = FALSE;

      return;
    }

    $this->followCursor = TRUE;

    if ($this->nav->matches($key, Action::MoveUp) || $this->nav->matches($key, Action::MoveDown) || $this->nav->matches($key, Action::MoveLeft) || $this->nav->matches($key, Action::MoveRight)) {
      $this->moveCursor($key);
    }
    elseif ($this->nav->matches($key, Action::Back)) {
      if ($this->navigator->current()->isModal()) {
        $this->closeModal(TRUE);
      }
      elseif ($this->navigator->pop()) {
        $this->cursor = 0;
      }
    }
    elseif ($this->nav->matches($key, Action::Activate)) {
      $this->activate();
    }
  }

  /**
   * Move the selection cursor for a directional key.
   *
   * Fields and buttons keep the linear semantics: Up/Down step one item and
   * Left/Right move within the button pair. Inside a panel grid the arrows
   * become spatial - Left/Right walk a row's columns and Up/Down jump between
   * rows (and out to the fields above or the buttons below), landing on the
   * nearest column.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The directional key.
   */
  protected function moveCursor(Key $key): void {
    $panel = $this->navigator->current();
    $items = $this->itemCountFor($panel);
    $count = $items + ($this->buttonsVisible() ? self::BUTTON_COUNT : 0);
    $fields = count($this->visibleFields($panel));
    $up = $this->nav->matches($key, Action::MoveUp);
    $down = $this->nav->matches($key, Action::MoveDown);

    if ($panel->layout !== [] && $this->cursor >= $fields && $this->cursor < $items) {
      [$row, $column] = $this->gridPosition($panel->layout, $this->cursor - $fields);

      if ($up) {
        $this->cursor = $row > 0 ? $fields + $this->gridSlot($panel->layout, $row - 1, $column) : ($fields > 0 ? $fields - 1 : $this->cursor);
      }
      elseif ($down) {
        $this->cursor = $row < count($panel->layout) - 1 ? $fields + $this->gridSlot($panel->layout, $row + 1, $column) : ($this->buttonsVisible() ? $items : $this->cursor);
      }
      elseif ($this->nav->matches($key, Action::MoveLeft)) {
        $this->cursor = $column > 0 ? $this->cursor - 1 : $this->cursor;
      }
      else {
        $this->cursor = $column < $panel->layout[$row] - 1 ? $this->cursor + 1 : $this->cursor;
      }

      return;
    }

    if ($up) {
      $this->cursor = max(0, $this->cursor - 1);
    }
    elseif ($down) {
      $this->cursor = min(max(0, $count - 1), $this->cursor + 1);
    }
    elseif ($this->buttonsVisible() && $this->cursor >= $items) {
      // The submit/cancel buttons are inline, so Left/Right moves between them.
      $delta = $this->nav->matches($key, Action::MoveRight) ? 1 : -1;
      $this->cursor = max($items, min($count - 1, $this->cursor + $delta));
    }
  }

  /**
   * The grid row and column a sub-panel offset sits at for a layout.
   *
   * @param list<int> $layout
   *   The layout rows.
   * @param int $offset
   *   The sub-panel offset within the panel (0-based).
   *
   * @return array{int,int}
   *   The [row, column] position.
   */
  protected function gridPosition(array $layout, int $offset): array {
    $start = 0;

    foreach ($layout as $row => $columns) {
      if ($offset < $start + $columns) {
        return [$row, $offset - $start];
      }

      $start += $columns;
    }

    // The builder guarantees the layout covers every sub-panel.
    // @codeCoverageIgnoreStart
    return [max(0, count($layout) - 1), 0];
    // @codeCoverageIgnoreEnd
  }

  /**
   * The sub-panel offset of a grid position, clamped to the row's columns.
   *
   * @param list<int> $layout
   *   The layout rows.
   * @param int $row
   *   The target row.
   * @param int $column
   *   The desired column; a narrower target row lands on its last column.
   *
   * @return int
   *   The sub-panel offset.
   */
  protected function gridSlot(array $layout, int $row, int $column): int {
    $start = 0;

    for ($index = 0; $index < $row; $index++) {
      $start += $layout[$index];
    }

    return $start + min($column, $layout[$row] - 1);
  }

  /**
   * Activate the selected item: edit a field or drill into a sub-panel.
   */
  protected function activate(): void {
    $panel = $this->navigator->current();
    $fields = $this->visibleFields($panel);
    $field_count = count($fields);

    if ($this->cursor < $field_count) {
      $this->openEditor($fields[$this->cursor]);

      return;
    }

    $subpanel = $panel->panels[$this->cursor - $field_count] ?? NULL;
    if ($subpanel instanceof Panel) {
      $this->enterPanel($subpanel);

      return;
    }

    if ($this->buttonsVisible()) {
      $this->activateButton($this->cursor - $field_count - count($panel->panels));
    }
  }

  /**
   * Enter a sub-panel: open it as a modal dialog, or drill into it.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The panel to enter.
   */
  protected function enterPanel(Panel $panel): void {
    if ($panel->isModal()) {
      $this->openModal($panel);

      return;
    }

    $this->navigator->enter($panel);
    $this->cursor = 0;
    $this->resolveLoaders($panel);
  }

  /**
   * Resolve a just-entered panel's option loaders, showing them loading.
   *
   * Paints the panel once - the loading fields read as "Loading…" through the
   * theme - then runs each loader (blocking) and settles, so drilling into a
   * panel loads its data on entry rather than up front.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The entered panel.
   */
  protected function resolveLoaders(Panel $panel): void {
    $loading = array_values(array_filter($panel->fields, static fn(Field $field): bool => $field->optionsLoader instanceof \Closure));

    if ($loading === []) {
      return;
    }

    if (isset($this->terminal)) {
      $this->terminal->render($this->positioned($this->frame($this->rows($this->terminal)), $this->terminal));
    }

    $this->engine->loadOptions($loading);
    $this->resettle();
  }

  /**
   * Open a modal dialog, snapshotting answers so a cancel can restore them.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
   *   The modal panel.
   */
  protected function openModal(Panel $panel): void {
    $this->modalValues = $this->values;
    $this->modalProvenance = $this->provenance;
    $this->modalReturnCursor = $this->cursor;
    $this->modalReturnOffset = $this->offset;
    $this->navigator->enter($panel);
    $this->cursor = 0;
    $this->offset = 0;
    $this->resolveLoaders($panel);
  }

  /**
   * Close the current modal dialog, restoring the parent's cursor and scroll.
   *
   * @param bool $cancel
   *   TRUE to discard the dialog's edits (restoring the opening snapshot);
   *   FALSE to keep them.
   */
  protected function closeModal(bool $cancel): void {
    if ($cancel) {
      $this->values = $this->modalValues;
      $this->provenance = $this->modalProvenance;
    }

    $this->navigator->pop();
    $this->cursor = $this->modalReturnCursor;
    $this->offset = $this->modalReturnOffset;
    $this->followCursor = TRUE;
    $this->modalValues = [];
    $this->modalProvenance = [];
    $this->modalReturnCursor = 0;
    $this->modalReturnOffset = 0;

    // A restored snapshot (or the dialog's kept edits) may change what is
    // active outside the dialog, so the parent re-settles before it renders.
    $this->resettle();
  }

  /**
   * The panel-hub footer: the contextual hint line, unless turned off.
   *
   * @return list<string>
   *   The footer lines: one when the footer is on, none when it is off.
   */
  protected function hubFooter(): array {
    return $this->footer ? [$this->theme->renderHints($this->nav, ...$this->navigationHints())] : [];
  }

  /**
   * The footer while a field is edited inline: the active widget's own hints.
   *
   * The keys in play are the widget's, not the hub's, so the footer switches to
   * the widget's hints against its field-scope bindings - the same line the
   * standalone editor would show.
   *
   * @return list<string>
   *   The widget's hint line, or none when the footer is turned off.
   */
  protected function inlineEditFooter(): array {
    if (!$this->footer || !$this->editor instanceof WidgetInterface || !$this->editing instanceof Field) {
      return [];
    }

    return [$this->theme->renderHints($this->keymap->forField($this->editing->type), ...$this->editor->hints())];
  }

  /**
   * The hint fragments for the panel hub, in display order.
   *
   * A panel grid navigates spatially, so its move hint carries the horizontal
   * arrows too.
   *
   * @return list<\DrevOps\Tui\Input\Hint>
   *   The hub hints.
   */
  protected function navigationHints(): array {
    $move = $this->navigator->current()->layout !== [] ? new Hint('move', Action::MoveUp, Action::MoveDown, Action::MoveLeft, Action::MoveRight) : new Hint('move', Action::MoveUp, Action::MoveDown);

    return [
      $move,
      new Hint('select', Action::Activate),
      new Hint('back', Action::Back),
      new Hint('quit', Action::Quit),
      new Hint('help', Action::Help),
    ];
  }

  /**
   * The help-overlay sections: the hub, then each widget type the form uses.
   *
   * Field types are listed once, in first-seen order, so the overlay teaches
   * every widget the form can show without repeating a type.
   *
   * @return list<\DrevOps\Tui\Render\HelpSection>
   *   The sections.
   */
  protected function helpSections(): array {
    $sections = [new HelpSection(Translator::t('Navigation'), $this->nav, ...$this->navigationHints())];

    $seen = [];
    foreach ($this->form->fields() as $field) {
      if (in_array($field->type, $seen, TRUE)) {
        continue;
      }

      $seen[] = $field->type;
      $widget = $this->widgets->create($field, $this->values[$field->id] ?? $field->default);
      $sections[] = new HelpSection($field->type->label(), $this->keymap->forField($field->type), ...$widget->hints());
    }

    return $sections;
  }

  /**
   * Whether the submit/cancel buttons are shown on the current panel.
   *
   * They live on the root panel only, so sub-panels are not cluttered with
   * global actions.
   *
   * @return bool
   *   TRUE when buttons are enabled and the navigator is at the root panel, or
   *   when the current panel is a modal (which always shows its own pair).
   */
  protected function buttonsVisible(): bool {
    if ($this->navigator->current()->isModal()) {
      return TRUE;
    }

    return $this->form->buttons->show && $this->navigator->isRoot();
  }

  /**
   * Activate a button by its index in the pair.
   *
   * In a modal the pair dismisses the dialog; otherwise it finishes the form,
   * recording whether the user cancelled.
   *
   * @param int $index
   *   The button index (submit first, cancel second).
   */
  protected function activateButton(int $index): void {
    if ($this->navigator->current()->isModal()) {
      $this->closeModal($index === self::CANCEL_BUTTON);

      return;
    }

    $this->done = TRUE;
    $this->cancelled = $index === self::CANCEL_BUTTON;
  }

  /**
   * Open the editor for a field.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   */
  protected function openEditor(Field $field): void {
    $this->editing = $field;
    $this->editor = $this->widgets->create($field, $this->values[$field->id] ?? $field->default, $this->values);
  }

  /**
   * Close the editor.
   */
  protected function closeEditor(): void {
    $this->editor = NULL;
    $this->editing = NULL;
  }

}
