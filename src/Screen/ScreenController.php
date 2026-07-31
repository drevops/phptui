<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\CancelException;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Drives one screen through a terminal session.
 *
 * Keys travel inward and drawing travels outward, and this is what turns the
 * two into a session: it assembles the screen around a panel, opens the
 * terminal, and then does one thing at a time - draw the screen, read a key,
 * hand it to the router, draw again - until the form ends.
 *
 * Two kinds of key stop here rather than reaching the router, and both for the
 * same reason: they act on something outside the screen. Pressing a button ends
 * the session, and activating work runs it against the terminal a step at a
 * time. A panel knows about neither, and a block never learns where it is
 * drawn, so the cooperative repaint between one step of the work and the next
 * belongs to whoever owns the terminal.
 *
 * How the session ends is the whole of what a caller sees: finishing it hands
 * back the answers, abandoning it raises {@see \DrevOps\Tui\CancelException},
 * and the interrupt key raises {@see \DrevOps\Tui\InterruptException} - so
 * partial answers are never mistaken for a completed form.
 *
 * @package DrevOps\Tui\Screen
 */
class ScreenController {

  /**
   * The region a panel's own rows, and the screen's panel, are drawn in.
   */
  protected const string CONTENT = 'content';

  /**
   * The rules a frame spends on the border it is drawn in, top and bottom.
   */
  protected const int FRAME_RULES = 2;

  /**
   * The screen the panel is drawn on.
   */
  protected Screen $screen;

  /**
   * The screen a field's help is drawn on instead.
   */
  protected Screen $overlay;

  /**
   * What each key press is handed to.
   */
  protected KeyRouter $router;

  /**
   * What draws a screen.
   */
  protected ScreenRenderer $renderer;

  /**
   * The trail of panels entered to get where the cursor is.
   */
  protected Breadcrumb $breadcrumb;

  /**
   * The keys that apply right now.
   */
  protected Legend $legend;

  /**
   * The buttons that end the form.
   */
  protected Actions $actions;

  /**
   * Why the form cannot be ended yet, drawn above those buttons.
   */
  protected Markup $notice;

  /**
   * The help of the field offering it, drawn while it is showing.
   */
  protected Markup $help;

  /**
   * What keeps the row the cursor is on inside the space its region has.
   */
  protected Scroller $scroller;

  /**
   * The bindings the whole screen answers to.
   */
  protected KeyMap $keys;

  /**
   * What resolves the answers the form opens on.
   */
  protected Collector $collector;

  /**
   * The terminal, for as long as the session is running.
   */
  protected ?Terminal $terminal = NULL;

  /**
   * How each answer came to be, keyed by field id.
   *
   * @var array<string,\DrevOps\Tui\Answers\Provenance>
   */
  protected array $provenance = [];

  /**
   * Which fields are there at all, keyed by field id.
   *
   * @var array<string,bool>
   */
  protected array $active = [];

  /**
   * Whether the session has ended.
   */
  protected bool $done = FALSE;

  /**
   * Whether the form was abandoned rather than finished.
   */
  protected bool $cancelled = FALSE;

  /**
   * Whether the session ended on the interrupt key.
   */
  protected bool $interrupted = FALSE;

  /**
   * Construct a controller.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel the screen starts in: the tree a form declares.
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme every block draws through.
   * @param array<string,mixed> $supplied
   *   Values supplied for its fields, keyed by field id.
   * @param \DrevOps\Tui\Input\KeyMap|null $keys
   *   The bindings the whole screen answers to, or NULL for the default preset.
   * @param \DrevOps\Tui\Screen\Collector|null $collector
   *   What resolves the answers the form opens on, or NULL for one that reuses
   *   no behaviour and applies no rules once they have settled.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run this session belongs to.
   * @param string $layout
   *   The layout the screen is arranged by.
   * @param \DrevOps\Tui\Theme\Border $border
   *   The frame drawn around every region at once.
   * @param bool $clearOnExit
   *   Whether the screen is cleared as the session ends.
   */
  public function __construct(
    protected Panel $panel,
    protected ThemeInterface $theme,
    protected array $supplied = [],
    ?KeyMap $keys = NULL,
    ?Collector $collector = NULL,
    protected Context $context = new Context(),
    string $layout = 'default',
    protected Border $border = Border::None,
    protected bool $clearOnExit = TRUE,
  ) {
    $this->keys = $keys ?? KeyMapManager::create();
    $this->collector = $collector ?? new Collector();
    $this->scroller = new Scroller();
    $this->renderer = new ScreenRenderer($theme, $border);

    $assembler = new Assembler();
    $this->screen = $assembler->assemble($panel, $layout);
    $this->breadcrumb = $this->furniture('header', Breadcrumb::class);
    $this->legend = $this->furniture('footer', Legend::class);

    $this->actions = $this->buttons($assembler);
    $this->notice = new Markup('screen-notice', '');
    $this->help = (new Markup('screen-help', ''))->bordered();
    $this->overlay = $this->helpScreen($layout);

    // The buttons end the form rather than the panel the cursor happens to be
    // in, so they go among the outermost panel's own rows: going into a nested
    // one leaves them behind exactly as it leaves that panel's siblings behind.
    if ($panel->currentButtons()->show) {
      $this->place($panel)->add($this->notice)->add($this->actions);
    }

    $this->router = (new KeyRouter($panel))->bind($this->keys);

    $this->seed();
  }

  /**
   * Run the session against a terminal until the form ends.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\InterruptException
   *   When the session was aborted with the interrupt key.
   * @throws \DrevOps\Tui\CancelException
   *   When the form was abandoned through its cancel button.
   */
  public function run(Terminal $terminal): Answers {
    $parser = new KeyParser();
    $this->terminal = $terminal;
    $terminal->setup();

    try {
      $opening = $this->opening();

      if ($opening !== '') {
        $terminal->render($opening);
      }

      while (!$this->done) {
        $this->paint();
        $bytes = $terminal->read();

        // An empty read means the input is exhausted - the scripted keys ran
        // out, or the stream closed. Stop rather than spin on the same frame.
        if ($bytes === '') {
          break;
        }

        foreach ($parser->parse($bytes) as $key) {
          // The interrupt aborts from anywhere, including from inside an open
          // field, so it is answered above the routing and drops straight out
          // to the teardown with the answers as they stand.
          if ($key->is(KeyName::Interrupt)) {
            $this->interrupted = TRUE;

            break 2;
          }

          $this->handle($key);
        }
      }
    }
    finally {
      $terminal->restore();

      // An abort always leaves a clean screen, even where a consumer opted out
      // of the clear for a session that ends normally.
      if ($this->clearOnExit || $this->interrupted) {
        $terminal->clear();
      }
    }

    if ($this->interrupted) {
      throw new InterruptException('The interactive session was interrupted.');
    }

    if ($this->cancelled) {
      throw new CancelException('The interactive session was cancelled.');
    }

    return $this->answers();
  }

  /**
   * Send one key where it belongs.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   */
  public function handle(Key $key): void {
    // Any key dismisses help, so it is spent there before anything else reads
    // it: a reader who asked for help never has to find the way out.
    if ($this->router->isShowingHelp()) {
      $this->router->handle($key);

      return;
    }

    $focused = $this->router->focused();

    if ($focused instanceof Actions && $this->pressed($focused, $key)) {
      return;
    }

    if ($focused instanceof Progress && $this->activates($key)) {
      $this->work($focused);

      return;
    }

    $before = $focused instanceof Field ? $focused->value() : NULL;

    $this->router->handle($key);

    $this->stamp($focused, $before);
  }

  /**
   * The answers as they stand: the values of the fields that are there.
   *
   * A field a condition hides keeps the value it settled on - so it surfaces
   * intact if the condition is satisfied again - but contributes no answer,
   * which is what a collection with no screen at all also hands back.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The answers, each describing the question it answers.
   */
  public function answers(): Answers {
    $values = [];
    $provenance = [];

    foreach (Tree::fields($this->panel) as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      if (!($this->active[$field->id()] ?? TRUE)) {
        continue;
      }

      $values[$field->id()] = $field->value();

      if (isset($this->provenance[$field->id()])) {
        $provenance[$field->id()] = $this->provenance[$field->id()];
      }
    }

    return Answers::forTree($this->panel, $values, $provenance);
  }

  /**
   * Whether the form was abandoned rather than finished.
   *
   * @return bool
   *   TRUE when the cancel button ended it.
   */
  public function isCancelled(): bool {
    return $this->cancelled;
  }

  /**
   * Whether the session ended on the interrupt key.
   *
   * @return bool
   *   TRUE when it did.
   */
  public function isInterrupted(): bool {
    return $this->interrupted;
  }

  /**
   * What is drawn before the first frame, if anything is.
   *
   * The start banner - and whatever key dismisses it - lands here.
   *
   * @return string
   *   The frame, empty when the session opens straight onto the form.
   */
  protected function opening(): string {
    return '';
  }

  /**
   * What is drawn instead of the frame when the terminal cannot hold it.
   *
   * The notice asking for a bigger terminal lands here.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return string
   *   The notice, empty when the frame is drawn as it is.
   */
  protected function guard(Terminal $terminal): string {
    return '';
  }

  /**
   * Place a drawn frame within the terminal area.
   *
   * Where a frame smaller than the terminal is anchored, and the padding a
   * spacing mode puts around what it holds, both land here.
   *
   * @param string $frame
   *   The drawn frame.
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return string
   *   The placed frame.
   */
  protected function chrome(string $frame, Terminal $terminal): string {
    return $frame;
  }

  /**
   * Style a passage of prose this controller composes.
   *
   * The markdown subset a description or a note may carry lands here.
   *
   * @param string $text
   *   The source text.
   *
   * @return string
   *   The styled text.
   */
  protected function prose(string $text): string {
    return $text;
  }

  /**
   * Draw the current frame.
   */
  protected function paint(): void {
    $terminal = $this->terminal;

    // One key at a time needs no terminal, so until a session is running there
    // is nowhere to draw and nothing is drawn.
    if (!$terminal instanceof Terminal) {
      return;
    }

    $notice = $this->guard($terminal);

    $terminal->render($notice === '' ? $this->chrome($this->frame($terminal), $terminal) : $notice);
  }

  /**
   * The frame as it stands: the furniture rewritten, then drawn outward.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal the frame is sized against.
   *
   * @return string
   *   The frame.
   */
  protected function frame(Terminal $terminal): string {
    $rows = $terminal->height();
    $columns = $this->columns($terminal);
    $helping = $this->router->helping();

    // Both are read out of the router rather than written beside it, so the
    // trail and the keys on offer can never disagree with where the cursor is.
    $this->breadcrumb->trail(...$this->router->trail());
    $this->router->refresh($this->legend);

    if ($helping instanceof Field) {
      // Help can run to paragraphs, so it replaces the panel rather than
      // crowding the row that offers it.
      $this->help->body($this->prose(Translator::t($helping->label()) . "\n\n" . Translator::t($helping->helpText())));

      return $this->renderer->render($this->overlay, $rows, $columns);
    }

    $this->follow($rows);

    return $this->renderer->render($this->screen, $rows, $columns);
  }

  /**
   * The columns a frame is laid out to.
   *
   * A row sized past the terminal hard-wraps onto the next line and corrupts
   * the layout below it, so a frame is never laid out wider than there is room
   * for - nor wider than the width a panel reads well at.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return int
   *   The columns.
   */
  protected function columns(Terminal $terminal): int {
    $width = $terminal->width();

    return $width > 0 ? min(DefaultTheme::DEFAULT_WIDTH, $width) : DefaultTheme::DEFAULT_WIDTH;
  }

  /**
   * Move each scrolling region so the row the cursor is on stays in sight.
   *
   * @param int $rows
   *   The terminal rows.
   */
  protected function follow(int $rows): void {
    $panel = $this->router->current();
    $focused = $this->router->focused();
    $sizes = $panel->currentLayout()->arrange($this->content($rows));

    foreach ($panel->currentLayout()->names() as $name) {
      $region = $panel->in($name);

      if (!$region->isScrolling()) {
        continue;
      }

      [$total, $row] = $this->measure($region, $focused);

      // A region the cursor is not in stays where it was left: only the one
      // holding the focused row has anything to follow.
      if ($row < 0) {
        continue;
      }

      $height = $sizes[$name] ?? 0;
      $region->scrollTo($this->scroller->follow($total, $height, $row, $region->offset($total, $height))->offset);
    }
  }

  /**
   * The rows the panel you are in is drawn into.
   *
   * Every panel entered on the way in is given the whole of the region it sits
   * in, so how deep the cursor has gone changes nothing about the arithmetic.
   *
   * @param int $rows
   *   The terminal rows.
   *
   * @return int
   *   The rows.
   */
  protected function content(int $rows): int {
    // A frame spends a rule top and bottom, so what the layout is given is the
    // terminal less its chrome.
    $inside = $this->border === Border::None ? $rows : max(0, $rows - self::FRAME_RULES);

    return $this->screen->currentLayout()->arrange($inside)[self::CONTENT] ?? $inside;
  }

  /**
   * The rows a region's blocks come to, and which one the cursor is on.
   *
   * @param \DrevOps\Tui\Screen\Region $region
   *   The region.
   * @param \DrevOps\Tui\Block\Capability\FocusCapableInterface|null $focused
   *   The block with the cursor, if any has it.
   *
   * @return array{int,int}
   *   The rows the blocks come to, and the first row of the focused one - or
   *   -1 when the cursor is not in this region at all.
   */
  protected function measure(Region $region, ?FocusCapableInterface $focused): array {
    $total = 0;
    $row = -1;

    foreach ($region->blocks() as $block) {
      if ($block === $focused) {
        $row = $total;
      }

      $total += substr_count($block->render($this->theme), "\n") + 1;
    }

    return [$total, $row];
  }

  /**
   * Do what a key does on the buttons that end the form.
   *
   * @param \DrevOps\Tui\Block\Actions $actions
   *   The buttons.
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return bool
   *   TRUE when the key was spent here, so it travels no further.
   */
  protected function pressed(Actions $actions, Key $key): bool {
    $bindings = $this->router->current()->bindings();

    // The buttons sit on one row, so the horizontal keys are what walks them.
    if ($bindings->matches($key, Action::MoveLeft)) {
      $this->step($actions, -1);

      return TRUE;
    }

    if ($bindings->matches($key, Action::MoveRight)) {
      $this->step($actions, 1);

      return TRUE;
    }

    if (!$this->activates($key)) {
      return FALSE;
    }

    $this->press($actions);

    return TRUE;
  }

  /**
   * Whether a key selects whatever the cursor is on.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return bool
   *   TRUE when it does.
   */
  protected function activates(Key $key): bool {
    return $this->router->current()->bindings()->matches($key, Action::Activate);
  }

  /**
   * Move the cursor along the buttons, stopping at the ends.
   *
   * @param \DrevOps\Tui\Block\Actions $actions
   *   The buttons.
   * @param int $delta
   *   The buttons to move by.
   */
  protected function step(Actions $actions, int $delta): void {
    $names = $actions->names();
    $at = array_search($actions->selected(), $names, TRUE);
    $next = $names[max(0, min(count($names) - 1, (is_int($at) ? $at : 0) + $delta))] ?? NULL;

    if ($next !== NULL) {
      $actions->select($next);
    }
  }

  /**
   * End the form, or say why it cannot be ended yet.
   *
   * @param \DrevOps\Tui\Block\Actions $actions
   *   The buttons.
   */
  protected function press(Actions $actions): void {
    $ending = Ending::tryFrom((string) $actions->selected());

    // Abandoning the form is always allowed: only finishing it has to answer
    // for the fields that are owed an answer.
    $owed = $ending === Ending::Cancel ? NULL : $this->owed();

    $actions->refuse($owed);
    $this->notice->body((string) $owed);

    if (!$actions->activate()) {
      return;
    }

    $this->done = TRUE;
    $this->cancelled = $ending === Ending::Cancel;
  }

  /**
   * The first field that is owed an answer and has none, and what it says.
   *
   * A field nobody opened never refused anything, so its own guard never ran on
   * it; this is where an answer that was never given is caught instead.
   *
   * @return string|null
   *   The reason, or NULL when every field that is there is answered.
   */
  protected function owed(): ?string {
    foreach (Tree::fields($this->panel) as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      if (!($this->active[$field->id()] ?? TRUE)) {
        continue;
      }

      $missing = $field->requiredViolation($field->value());

      if ($missing !== NULL) {
        return $missing;
      }
    }

    return NULL;
  }

  /**
   * Run a progress block's work, drawing its indicator as it advances.
   *
   * @param \DrevOps\Tui\Block\Progress $progress
   *   The block.
   */
  protected function work(Progress $progress): void {
    $workload = $progress->workload();

    if (!$workload instanceof \Closure) {
      return;
    }

    // The indicator starts before the work does, so the row says something is
    // happening from the first blocking step rather than after the last.
    $this->paint();

    $workload(new ProgressReporter(function (?string $label) use ($progress): void {
      $progress->advance(1, $label);
      $this->paint();
    }));
  }

  /**
   * Record how an answer came to be, once somebody has changed it.
   *
   * @param \DrevOps\Tui\Block\Capability\FocusCapableInterface|null $focused
   *   The block the key was handed to, if any had the cursor.
   * @param mixed $before
   *   The answer it held before the key reached it.
   */
  protected function stamp(?FocusCapableInterface $focused, mixed $before): void {
    if (!$focused instanceof Field || $focused->value() === $before) {
      return;
    }

    // Changing a field that computes its answer pins the rule against being
    // recomputed, exactly as supplying a value to it does.
    $this->provenance[$focused->id()] = $focused->derivation() instanceof Derive ? Provenance::Override : Provenance::Edited;

    // A refusal describes the answers as they stood when the button was
    // pressed, so a change of any kind retires it rather than leaving it to
    // contradict what the screen now shows.
    $this->actions->refuse(NULL);
    $this->notice->body('');
  }

  /**
   * Resolve every answer the form opens on.
   *
   * The values a collection with no screen at all would arrive at, put onto the
   * blocks that hold them: one declaration reaches both paths, so a form opens
   * showing exactly what it would have answered headlessly.
   */
  protected function seed(): void {
    [$values, $provenance, $active] = $this->collector->seed($this->panel, $this->supplied, $this->context);

    foreach (Tree::fields($this->panel) as $field) {
      if (array_key_exists($field->id(), $values)) {
        $field->default($values[$field->id()]);
      }
    }

    $this->provenance = $provenance;
    $this->active = $active;
  }

  /**
   * The buttons that end the form, labelled as the panel declares them.
   *
   * @param \DrevOps\Tui\Screen\Assembler $assembler
   *   The assembler that builds the standard pair.
   *
   * @return \DrevOps\Tui\Block\Actions
   *   The buttons.
   */
  protected function buttons(Assembler $assembler): Actions {
    $declared = $this->panel->currentButtons();

    // The assembler says which buttons a form has; the panel says what they
    // read, so a form that renamed its submit gets the name it chose.
    return $assembler->actions()
      ->action(Ending::Submit->value, Translator::t($declared->submitLabel))
      ->action(Ending::Cancel->value, Translator::t($declared->cancelLabel));
  }

  /**
   * The screen a field's help is drawn on.
   *
   * The trail and the keys on offer are the same blocks the panel's own screen
   * draws, so asking for help changes what is being read and nothing else.
   *
   * @param string $layout
   *   The layout the screen is arranged by.
   *
   * @return \DrevOps\Tui\Screen\Screen
   *   The screen.
   */
  protected function helpScreen(string $layout): Screen {
    $screen = (new Screen())->layout(LayoutManager::create($layout));

    $screen->in('header')->add($this->breadcrumb);
    $screen->in(self::CONTENT)->add($this->help);
    $screen->in('footer')->add($this->legend);

    return $screen;
  }

  /**
   * The region a panel's own rows are drawn in.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return \DrevOps\Tui\Screen\Region
   *   The region.
   */
  protected function place(Panel $panel): Region {
    $names = $panel->currentLayout()->names();

    // A layout that names a region for its rows takes them there; one that
    // names its regions anything else takes them in the first it declares.
    return $panel->in(in_array(self::CONTENT, $names, TRUE) ? self::CONTENT : ($names[0] ?? self::CONTENT));
  }

  /**
   * A piece of standard furniture the assembler put in a region.
   *
   * @param string $name
   *   The region name.
   * @param class-string<T> $kind
   *   The kind of block it is.
   *
   * @return T
   *   The block.
   *
   * @throws \LogicException
   *   When the region holds no block of that kind.
   *
   * @template T of \DrevOps\Tui\Block\BlockInterface
   */
  protected function furniture(string $name, string $kind): object {
    foreach ($this->screen->in($name)->blocks() as $block) {
      if ($block instanceof $kind) {
        return $block;
      }
    }

    // The assembler puts one of each into the screen it builds, so a screen
    // missing one never reaches a frame.
    // @codeCoverageIgnoreStart
    throw new \LogicException(sprintf('The assembled screen holds no %s in its "%s" region.', $kind, $name));
    // @codeCoverageIgnoreEnd
  }

}
