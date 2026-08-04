<?php

declare(strict_types=1);

namespace DrevOps\Tui\Screen;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Mode;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\CancelException;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Field\Capability\ExternalEditCapableInterface;
use DrevOps\Tui\Field\Capability\QueryOptionsCapableInterface;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\KeyParser;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\RenderMode;
use DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Primitive\Status;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Box;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\Overlay;
use DrevOps\Tui\Render\Scroller;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Screen\Layout\DefaultLayout;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Capability\DimCapableInterface;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
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
 * Three kinds of key stop here rather than reaching the router, and all three
 * for the same reason: they act on something outside the screen. Pressing a
 * button ends the form or closes the dialog it belongs to, activating work runs
 * it against the terminal a step at a time, and leaving is about the session
 * rather than about anything in it. A panel knows about none of them, and a
 * block never learns where it is drawn, so the cooperative repaint between one
 * step of the work and the next belongs to whoever owns the terminal.
 *
 * Every answer taken re-settles the form: rows that follow the answers resolve
 * again, computed values recompute, conditions decide who is there at all and
 * the rules that write a value re-apply - so a dependent row appears the moment
 * its condition holds, exactly as it does with no screen at all.
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
   * The id of the row saying why the form cannot be ended yet.
   */
  protected const string NOTICE = 'screen-notice';

  /**
   * The rules a frame spends on the border it is drawn in, top and bottom.
   */
  protected const int FRAME_RULES = 2;

  /**
   * The rows a dialog spends on its title and on the air under its contents.
   */
  protected const int DIALOG_RULES = 2;

  /**
   * The share of the screen's width a dialog leaves clear on each side.
   */
  protected const int DIALOG_INSET = 8;

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
   * What resolves the answers the form opens on, and settles them again after.
   */
  protected Collector $collector;

  /**
   * What hands a passage of text to an editor of the reader's own.
   */
  protected ExternalEditor $externalEditor;

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
   * The dialogs standing open, with the answers each was opened over.
   *
   * @var list<array{panel:\DrevOps\Tui\Block\Panel,values:array<string,mixed>,provenance:array<string,\DrevOps\Tui\Answers\Provenance>}>
   */
  protected array $dialogs = [];

  /**
   * The narrowest terminal the frame can be read in, once it was measured.
   */
  protected ?int $minimumWidth = NULL;

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
   * @param bool $footer
   *   Whether the keys that apply right now are advertised at all.
   * @param string $banner
   *   What is shown before the form, dismissed by any key; empty opens straight
   *   onto the first frame.
   * @param string $version
   *   The version shown under that banner.
   * @param \DrevOps\Tui\Render\ExternalEditor|null $external_editor
   *   What hands a passage of text to an editor of the reader's own, or NULL
   *   for one that launches whatever the environment names.
   */
  public function __construct(
    protected Panel $panel,
    protected ThemeInterface $theme,
    protected array $supplied = [],
    ?KeyMap $keys = NULL,
    ?Collector $collector = NULL,
    protected Context $context = new Context(),
    protected string $layout = 'default',
    protected Border $border = Border::None,
    protected bool $clearOnExit = TRUE,
    protected bool $footer = TRUE,
    protected string $banner = '',
    protected string $version = '',
    ?ExternalEditor $external_editor = NULL,
  ) {
    $this->keys = $keys ?? KeyMapManager::create();
    $this->collector = $collector ?? new Collector();
    $this->externalEditor = $external_editor ?? new ExternalEditor();
    $this->scroller = new Scroller();
    $this->renderer = new ScreenRenderer($theme, $border);

    $assembler = new Assembler();
    $this->screen = $assembler->assemble($panel, $this->layout);
    // A layout keeping no place for a piece still gets a live one: the trail
    // and the keys keep tracking the session, they are just never drawn.
    $this->breadcrumb = $this->furniture('header', Breadcrumb::class) ?? new Breadcrumb($panel->title());
    $this->legend = $this->furniture('footer', Legend::class) ?? (new Legend())->advertise($panel->bindings(), ...$panel->hints());

    $this->actions = $this->buttons($assembler, $panel);
    // A session opens on a form nobody has been refused yet, so whatever the
    // last one left standing there is cleared rather than carried in.
    $this->notice = ($this->stated($panel) ?? new Markup(self::NOTICE, ''))->body('');
    $this->help = (new Markup('screen-help', ''))->bordered();
    $this->overlay = $this->helpScreen();

    $this->dress($assembler);

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
    $terminal->setup($this->wash());

    try {
      $this->welcome($terminal, $parser);

      // The outermost panel is opened by the session starting rather than by a
      // key, so what it owes is fetched before the first frame is read.
      $this->furnish();

      while (!$this->done && !$this->interrupted) {
        $this->paint();
        $bytes = $terminal->read();

        // An empty read means the input is exhausted - the scripted keys ran
        // out, or the stream closed. Stop rather than spin on the same frame.
        if ($bytes === '') {
          break;
        }

        // A terminal that cannot hold the frame is showing a notice instead of
        // the form, so every key but the one that leaves is dropped rather than
        // changing something nobody can see.
        $guarded = $this->guard($terminal) !== '';

        foreach ($parser->parse($bytes) as $key) {
          // The interrupt aborts from anywhere, including from inside an open
          // field, so it is answered above the routing and drops straight out
          // to the teardown with the answers as they stand.
          if ($key->is(KeyName::Interrupt)) {
            $this->interrupted = TRUE;

            break 2;
          }

          if ($guarded && !$this->quits($key)) {
            continue;
          }

          $this->handle($key);
        }

        // Asked once the whole read is spent rather than once per key, so a
        // burst of typing - or a paste - costs the source one call.
        $this->query();
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

    if ($this->quits($key)) {
      $this->leave();

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

    // Read before the key reaches it: a row that was open before it and is
    // settled after it is a row whose answer has just been taken.
    $open = $this->editing();

    $this->router->handle($key);

    $this->handoff($open);
    $this->stamp($open);
    $this->synchronize();
    $this->furnish();
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
   * @return string
   *   The frame, empty when the session opens straight onto the form.
   */
  protected function opening(): string {
    if ($this->banner === '') {
      return '';
    }

    return $this->pieces()->renderBanner($this->banner, $this->version) . "\n\n" . Translator::t('Press any key to continue...');
  }

  /**
   * What is drawn instead of the frame when the terminal cannot hold it.
   *
   * Only a frame that takes the whole terminal can be too small for it: one
   * that takes what it needs is read by scrolling, however little room there
   * is. The notice is centred rather than anchored where the frame would be,
   * because the anchor places content on a screen the frame fits, which this
   * one is not.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return string
   *   The notice, empty when the frame is drawn as it is.
   */
  protected function guard(Terminal $terminal): string {
    $occupancy = $this->occupancy();

    if (!$occupancy instanceof OccupyCapableInterface || !$occupancy->isFullscreen()) {
      return '';
    }

    $columns = $this->narrowest($occupancy);
    $rows = $this->shortest($occupancy);

    if ($terminal->width() >= $columns && $terminal->height() >= $rows) {
      return '';
    }

    $lines = [
      $this->pieces()->renderStatus(Status::Error, Translator::t('Terminal too small.')),
      Translator::t('Need at least @width x @height - have @w x @h.', [
        '@width' => (string) $columns,
        '@height' => (string) $rows,
        '@w' => (string) $terminal->width(),
        '@h' => (string) $terminal->height(),
      ]),
      (new Legend())->advertise($this->router->bindings(), new Hint('quit', Action::Quit))->render($this->theme),
    ];

    $width = Ansi::blockWidth($lines);
    [$top, $left] = Overlay::center($terminal->width(), $terminal->height(), $width, count($lines));
    $backdrop = array_fill(0, max(count($lines), $terminal->height()), str_repeat(' ', max($width, $terminal->width())));

    return implode("\n", Overlay::composite($backdrop, $lines, $width, $top, $left));
  }

  /**
   * Place a drawn frame within the terminal area.
   *
   * A frame that takes what it needs is drawn where the cursor already is, as
   * anything written to a terminal is. One that takes the whole terminal and
   * then does not fill it - a capped frame, a banner, a help page - is anchored
   * where the theme says, padded with blank space on the sides it leaves.
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
    $occupancy = $this->occupancy();

    if (!$occupancy instanceof OccupyCapableInterface || !$occupancy->isFullscreen()) {
      return $frame;
    }

    $lines = explode("\n", $frame);
    $area_width = $terminal->width();
    $area_height = $terminal->height();
    $width = Ansi::blockWidth($lines);

    if (count($lines) >= $area_height && $width >= $area_width) {
      return $frame;
    }

    [$top, $left] = Overlay::place($area_width, $area_height, $width, count($lines), $occupancy->halign(), $occupancy->valign());
    $backdrop = array_fill(0, $area_height, str_repeat(' ', $area_width));

    return implode("\n", Overlay::composite($backdrop, $lines, $width, $top, $left));
  }

  /**
   * Show what precedes the form, and wait for the key that dismisses it.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   * @param \DrevOps\Tui\Input\KeyParser $parser
   *   What reads keys out of the bytes the terminal delivers.
   */
  protected function welcome(Terminal $terminal, KeyParser $parser): void {
    $opening = $this->opening();

    if ($opening === '') {
      return;
    }

    $terminal->render($this->chrome($opening, $terminal));

    // Any key gets past it, but the interrupt aborts here as it does anywhere
    // else rather than dropping a reader into a form they never asked for.
    foreach ($parser->parse($terminal->read()) as $key) {
      if ($key->is(KeyName::Interrupt)) {
        $this->interrupted = TRUE;

        return;
      }
    }
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
    $rows = $this->rows($terminal);
    $columns = $this->columns();
    $helping = $this->router->helping();

    // Both are read out of the router rather than written beside it, so the
    // trail and the keys on offer can never disagree with where the cursor is.
    $this->breadcrumb->trail(...$this->router->trail());
    $this->refresh();

    if ($helping instanceof Field) {
      // Help can run to paragraphs, so it replaces the panel rather than
      // crowding the row that offers it.
      $this->help->title(Translator::t($helping->label()))->body(Translator::t($helping->helpText()));

      return $this->renderer->render($this->overlay, $rows, $columns);
    }

    $this->follow($rows);

    $alone = $this->alone();

    if ($alone instanceof Field) {
      return $this->renderer->render($this->stage($alone), $rows, $columns);
    }

    if ($this->router->current()->isModal()) {
      return $this->overlaid($rows, $columns);
    }

    return $this->renderer->render($this->screen, $rows, $columns);
  }

  /**
   * The columns a frame is laid out to.
   *
   * The theme was built for a frame of a stated width and lays every row out
   * to it - a right-aligned badge, a rule, a wrapped description. Drawing that
   * frame to any other width puts the rows and the edge they were aligned
   * against in different places, so the width is read off the theme rather
   * than worked out a second time here; what the theme states is the room
   * inside the frame, and the border it asks for is drawn around that.
   *
   * @return int
   *   The columns.
   */
  protected function columns(): int {
    return $this->theme->contentWidth() + ($this->border === Border::None ? 0 : ScreenRenderer::CHROME);
  }

  /**
   * The rows a frame is laid out to.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal.
   *
   * @return int
   *   The rows.
   */
  protected function rows(Terminal $terminal): int {
    $occupancy = $this->occupancy();
    $tallest = $occupancy instanceof OccupyCapableInterface ? $occupancy->maxHeight() : 0;

    return $tallest > 0 ? min($terminal->height(), $tallest) : $terminal->height();
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

      [$total, $row] = $this->renderer->extent($region, $focused instanceof BlockInterface ? $focused : NULL);

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
   * Whether a key leaves where it is pressed.
   *
   * Asked of the keys that apply right now rather than of the whole map, which
   * is what leaves the key typing itself into an open field: the letter that
   * leaves a panel is a letter while something is collecting one.
   *
   * @param \DrevOps\Tui\Input\Key $key
   *   The key.
   *
   * @return bool
   *   TRUE when it does.
   */
  protected function quits(Key $key): bool {
    return $this->router->bindings()->matches($key, Action::Quit);
  }

  /**
   * Leave: close the dialog that is open, else end the session.
   *
   * Leaving is not abandoning. The answers stand exactly as they do on a
   * finished form, because somebody who has answered a form and left it has
   * still answered it.
   */
  protected function leave(): void {
    if ($this->router->current()->isModal()) {
      $this->dismiss(TRUE);

      return;
    }

    $this->done = TRUE;
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
   * End the form, close the dialog, or say why neither can happen yet.
   *
   * @param \DrevOps\Tui\Block\Actions $actions
   *   The buttons.
   */
  protected function press(Actions $actions): void {
    $ending = Ending::tryFrom((string) $actions->selected());

    // Inside a dialog the pair closes the dialog: what is behind it is still
    // being filled in, so neither button is about the form.
    if ($this->router->current()->isModal()) {
      $this->dismiss($ending === Ending::Cancel);

      return;
    }

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
   * Fetch what the panel you are now in owes, before anybody reads it.
   *
   * A set too large or too slow to hold is fetched when the panel holding it is
   * opened rather than when the form starts, so walking into one is what pays
   * for it - and the row says it is still coming while the call blocks.
   */
  protected function furnish(): void {
    if ($this->collector->load($this->router->current(), $this->paint(...))) {
      $this->resettle();
    }
  }

  /**
   * Ask an open row's source for the rows the query it holds names.
   *
   * Unlike a set fetched once, a query source is asked again as what is typed
   * changes - so the call belongs where the typing is read rather than where
   * the panel is opened.
   */
  protected function query(): void {
    $open = $this->editing();
    $editor = $open?->editor();
    $source = $open?->source();

    if (!$editor instanceof QueryOptionsCapableInterface || !$source instanceof \Closure) {
      return;
    }

    $query = $editor->pendingQuery();

    if ($query === NULL) {
      return;
    }

    $editor->beginQuery();
    $this->paint();

    try {
      $rows = Option::resolved($source($query, $this->values()));
      $editor->applyQuery($query, $rows);
      $open->settle($this->offered($open, $rows));
    }
    catch (\Throwable) {
      // Consumer code that cannot answer must not end a session over a terminal
      // still in raw mode: the row says so and stays open, and the query is
      // remembered so the same failing call is not made again on every frame.
      $editor->failQuery($query, Translator::t('Could not load options.'));
    }
  }

  /**
   * Everything a row has been offered so far, the latest query included.
   *
   * A query names a slice of a set rather than the set, so what it answers adds
   * to what earlier ones answered: a choice made under one query is still the
   * reader's when the next no longer offers it, and the row it stands for is
   * still there to measure it against.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param list<\DrevOps\Tui\Model\Option> $rows
   *   What the latest query answered.
   *
   * @return array<string,string>
   *   The label of every row offered so far, keyed by its value.
   */
  protected function offered(Field $field, array $rows): array {
    $offered = [];

    foreach ([...$field->entries(), ...$rows] as $row) {
      if ($row->kind === OptionKind::Option) {
        $offered[$row->value] = $row->label;
      }
    }

    return $offered;
  }

  /**
   * Hand what an open field holds to an editor of the reader's own.
   *
   * The field asks and the session answers: launching a program means leaving
   * the terminal to it and taking it back afterwards, which is the session's to
   * do and nothing a block could reach.
   *
   * @param \DrevOps\Tui\Block\Field|null $open
   *   The field the key reached, if it reached one that was open.
   */
  protected function handoff(?Field $open): void {
    if (!$open instanceof Field) {
      return;
    }

    $editor = $open->editor();

    if (!$editor instanceof ExternalEditCapableInterface || !$editor->wantsExternalEdit()) {
      return;
    }

    $held = $editor->value();
    $editor->applyExternalEdit($this->externalEditor->edit(is_string($held) ? $held : '', $this->terminal));

    // What came back is what is being typed, not what was accepted: the field
    // still has to be accepted before it becomes the answer.
    $open->draft($editor->value());
  }

  /**
   * Record how an answer came to be, once somebody has taken it.
   *
   * Taking an answer is what stamps it, whether or not the answer changed: a
   * reader who opened a row and accepted what was there has answered it.
   *
   * @param \DrevOps\Tui\Block\Field|null $open
   *   The field the key reached, if it reached one that was open.
   */
  protected function stamp(?Field $open): void {
    if (!$open instanceof Field || !$open->hasAccepted()) {
      return;
    }

    // Changing a field that computes its answer pins the rule against being
    // recomputed, exactly as supplying a value to it does.
    $this->provenance[$open->id()] = $open->derivation() instanceof Derive ? Provenance::Override : Provenance::Edited;

    // A refusal describes the answers as they stood when the button was
    // pressed, so a change of any kind retires it rather than leaving it to
    // contradict what the screen now shows.
    $this->actions->refuse(NULL);
    $this->notice->body('');

    $this->resettle();
  }

  /**
   * Keep track of the dialogs that opened and closed while a key was handled.
   *
   * A dialog is entered and left through the same keys everything else is, so
   * what tells one from the other is where the cursor ended up. Watching that
   * rather than intercepting the keys is what keeps the router's one rule -
   * inward to whatever binds it - true of a dialog too.
   */
  protected function synchronize(): void {
    $current = $this->router->current();

    // Going into a dialog is where the answers behind it are remembered, so
    // that whatever it does to them can be put back.
    if ($current->isModal() && $this->standing() !== $current) {
      $this->dialogs[] = ['panel' => $current, 'values' => $this->values(), 'provenance' => $this->provenance];
      $this->reset($current);

      return;
    }

    // A dialog left any other way than through its own buttons is abandoned,
    // so the answers behind it stand as they did when it opened.
    while ($this->dialogs !== [] && $this->standing() !== $current) {
      $this->discard();
    }
  }

  /**
   * Close the dialog that is open, keeping or discarding what it collected.
   *
   * @param bool $discard
   *   Whether the answers go back to what they were when it opened.
   */
  protected function dismiss(bool $discard): void {
    if ($discard) {
      $this->discard();
    }
    else {
      array_pop($this->dialogs);
      $this->resettle();
    }

    $this->router->leave();
  }

  /**
   * Put the answers back as they stood when the open dialog was opened.
   */
  protected function discard(): void {
    $dialog = array_pop($this->dialogs);

    if ($dialog === NULL) {
      // @codeCoverageIgnoreStart
      return;
      // @codeCoverageIgnoreEnd
    }

    foreach (Tree::fields($this->panel) as $field) {
      if (array_key_exists($field->id(), $dialog['values'])) {
        $field->default($dialog['values'][$field->id()]);
      }
    }

    $this->provenance = $dialog['provenance'];

    $this->resettle();
  }

  /**
   * The dialog standing open, if one is.
   *
   * @return \DrevOps\Tui\Block\Panel|null
   *   The panel it was opened from, or NULL when no dialog is open.
   */
  protected function standing(): ?Panel {
    return $this->dialogs === [] ? NULL : $this->dialogs[count($this->dialogs) - 1]['panel'];
  }

  /**
   * Rest a panel's own buttons back on the first of them.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   */
  protected function reset(Panel $panel): void {
    foreach ($panel->place()->blocks() as $block) {
      if ($block instanceof Actions) {
        $block->select(Ending::Submit->value);

        return;
      }
    }
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

    $this->settled();
  }

  /**
   * Settle the form again over the answers it now holds.
   *
   * The same stages the opening answers went through, so a row that follows the
   * answers narrows, a computed value recomputes, a condition shows or hides a
   * row and a rule that writes a value re-applies - the moment the answer they
   * read is taken rather than at the end of the form.
   */
  protected function resettle(): void {
    [$values, $active] = $this->collector->resettle($this->panel, $this->values(), $this->pinned(), $this->context);

    foreach (Tree::fields($this->panel) as $field) {
      if (array_key_exists($field->id(), $values)) {
        $field->default($values[$field->id()]);
      }
    }

    $this->active = $active;

    $this->settled();
    // In that order: what is there is worked out first, then the cursor is put
    // somewhere that still exists - out of a section that has just gone, and
    // then onto a row of whatever section it lands in.
    $this->router->resurface();
    $this->router->reframe();
  }

  /**
   * The answers as the blocks hold them, keyed by field id.
   *
   * @return array<string,mixed>
   *   The values, a row that only shows carrying none.
   */
  protected function values(): array {
    $values = [];

    foreach (Tree::fields($this->panel) as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      $values[$field->id()] = $field->value();
    }

    return $values;
  }

  /**
   * The answers of the rows that are there, keyed by field id.
   *
   * What a condition is measured against: a row that is not there answers
   * nothing, so it cannot decide whether another row is there either.
   *
   * @return array<string,mixed>
   *   The answers.
   */
  protected function answered(): array {
    $answers = [];

    foreach (Tree::fields($this->panel) as $field) {
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      if ($this->active[$field->id()] ?? FALSE) {
        $answers[$field->id()] = $field->value();
      }
    }

    return $answers;
  }

  /**
   * The computed answers that must not be recomputed, keyed by field id.
   *
   * A rule computes an answer until somebody answers over it, and detecting one
   * outside the form counts as answering it - so both pin the rule, and every
   * other computed answer follows it.
   *
   * @return array<string,bool>
   *   The pinned map.
   */
  protected function pinned(): array {
    $pinned = [];

    foreach (Tree::fields($this->panel) as $field) {
      if (!$field->derivation() instanceof Derive) {
        continue;
      }

      $provenance = $this->provenance[$field->id()] ?? Provenance::Default;
      $pinned[$field->id()] = $provenance === Provenance::Override || $provenance === Provenance::Detected;
    }

    return $pinned;
  }

  /**
   * Bring the screen into line with the answers that have just settled.
   *
   * Which rows are there at all and how each answer came to be are both facts
   * about the answers rather than about any block, so a block is told them
   * whenever they are worked out again. A section carries what it holds, so a
   * block is told what the answers say about it and about every section above
   * it, rather than about its own rule alone.
   */
  protected function settled(): void {
    $answers = $this->answered();
    $within = Tree::within($this->panel, $answers);

    foreach (Tree::panels($this->panel) as $panel) {
      foreach ($panel->blocks() as $block) {
        if ($block instanceof DependCapableInterface) {
          ($within[spl_object_id($block)] ?? TRUE) ? $block->reveal() : $block->hide();
        }
      }
    }

    foreach (Tree::fields($this->panel) as $field) {
      $provenance = $this->provenance[$field->id()] ?? Provenance::Default;

      // How an answer starts out is not news, so saying it of every untouched
      // row would badge the whole form and tell a reader nothing.
      $field->badge($provenance === Provenance::Default ? '' : $provenance->label());
    }
  }

  /**
   * Advertise the keys that apply right now, unless the form says not to.
   */
  protected function refresh(): void {
    if (!$this->footer) {
      $this->legend->clear();

      return;
    }

    $this->legend->advertise($this->router->bindings(), ...$this->hints());
  }

  /**
   * What the keys on offer do, in the order they are advertised.
   *
   * Two of them are the session's rather than any block's, which is why they
   * are added here: leaving is about the session, and help is a fact about the
   * question rather than about whatever is collecting the answer.
   *
   * @return list<\DrevOps\Tui\Input\Hint>
   *   The fragments.
   */
  protected function hints(): array {
    $open = $this->editing();
    $hints = $this->router->hints();

    // Never beside an open row's keys, where the same letter is something
    // being typed rather than a way out.
    if (!$open instanceof Field) {
      $hints[] = new Hint('quit', Action::Quit);
    }

    $asking = $open instanceof Field ? $open : $this->router->focused();

    if ($asking instanceof Field && $asking->helpText() !== '') {
      $hints[] = new Hint('show help', Action::Help);
    }

    return $hints;
  }

  /**
   * The field that is open, if one is.
   *
   * @return \DrevOps\Tui\Block\Field|null
   *   The field, or NULL when every row is settled.
   */
  protected function editing(): ?Field {
    $focused = $this->router->focused();

    return $focused instanceof Field && $focused->mode() === Mode::Edit ? $focused : NULL;
  }

  /**
   * The field that has the whole frame to itself, if one has.
   *
   * @return \DrevOps\Tui\Block\Field|null
   *   The field, or NULL when the panel is what is drawn.
   */
  protected function alone(): ?Field {
    $focused = $this->editing();

    if (!$focused instanceof Field) {
      return NULL;
    }

    return $focused->renderMode() === RenderMode::Standalone ? $focused : NULL;
  }

  /**
   * The screen a field that takes the whole frame is drawn on.
   *
   * The trail and the keys on offer are the blocks the panel's own screen
   * draws, so a field with the frame to itself is the same session with one row
   * in front of the reader instead of a list.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return \DrevOps\Tui\Screen\Screen
   *   The screen.
   */
  protected function stage(Field $field): Screen {
    // A field with the frame to itself always reads as trail, editor, keys -
    // whatever arrangement the session behind it uses.
    $screen = (new Screen())->layout(new DefaultLayout());

    $screen->in('header')->add($this->breadcrumb);
    $screen->in(self::CONTENT)->add($field);
    $screen->in('footer')->add($this->legend);

    return $screen;
  }

  /**
   * Draw the open dialog over the screen it was opened from.
   *
   * @param int $rows
   *   The terminal rows.
   * @param int $columns
   *   The columns the frame is laid out to.
   *
   * @return string
   *   The frame.
   */
  protected function overlaid(int $rows, int $columns): string {
    $modal = $this->router->current();

    // What is behind the dialog is the screen as it was before it opened, the
    // row the dialog was opened from included.
    $modal->leave();
    $behind = explode("\n", $this->renderer->render($this->screen, $rows, $columns));
    $modal->enter();

    $inset = max(2, intdiv($columns, self::DIALOG_INSET));
    $width = max(1, $columns - 2 * $inset);
    $box = explode("\n", $this->dialog($modal, $rows, $width));

    // Only plain text can be sliced by column, and what shows through beside
    // the dialog is sliced on every row it covers.
    $backdrop = array_map(static fn(string $line): string => Box::fit(Ansi::strip($line), $columns), $behind);

    [$top, $left] = Overlay::center($columns, count($backdrop), $width, count($box));

    return implode("\n", Overlay::composite($backdrop, $box, $width, $top, $left, $this->recede(...)));
  }

  /**
   * Draw the dialog itself: its title over what it holds, inside a border.
   *
   * @param \DrevOps\Tui\Block\Panel $modal
   *   The panel the dialog is.
   * @param int $rows
   *   The terminal rows.
   * @param int $columns
   *   The columns the dialog is drawn in.
   *
   * @return string
   *   The dialog.
   */
  protected function dialog(Panel $modal, int $rows, int $columns): string {
    // A dialog is one thing to read under its title, whatever arrangement the
    // session behind it uses.
    $screen = (new Screen())->layout(new DefaultLayout());

    $screen->in('header')->add(new Breadcrumb($modal->title()));
    $screen->in(self::CONTENT)->add($modal);

    // A dialog is as tall as what it holds - it is one thing to read rather
    // than a list to scroll - up to as much of it as the terminal can show.
    $height = min($rows, $this->depth($modal) + self::FRAME_RULES + self::DIALOG_RULES);

    // A dialog with no edge is a dialog nobody can see the edge of, so a form
    // that draws no frame still draws one around what floats over it.
    $renderer = new ScreenRenderer($this->theme, $this->border === Border::None ? Border::Line : $this->border);

    return $renderer->render($screen, $height, $columns);
  }

  /**
   * The rows a panel's own blocks come to.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return int
   *   The rows.
   */
  protected function depth(Panel $panel): int {
    $rows = 0;

    foreach ($panel->currentLayout()->names() as $name) {
      [$total] = $this->renderer->extent($panel->in($name));
      $rows += $total;
    }

    return $rows;
  }

  /**
   * Push back a run of what shows from behind whatever is drawn over it.
   *
   * @param string $segment
   *   The run.
   *
   * @return string
   *   The receded run.
   */
  protected function recede(string $segment): string {
    return $this->theme instanceof DimCapableInterface ? $this->theme->dim($segment) : $segment;
  }

  /**
   * The wash the whole terminal is filled with, behind everything drawn.
   *
   * @return string|null
   *   The background, or NULL to keep the terminal's own.
   */
  protected function wash(): ?string {
    $occupancy = $this->occupancy();

    return $occupancy instanceof OccupyCapableInterface ? $occupancy->background() : NULL;
  }

  /**
   * The theme, when it says how much of the terminal it takes.
   *
   * @return \DrevOps\Tui\Theme\Capability\OccupyCapableInterface|null
   *   The theme, or NULL when it says nothing about the terminal at all.
   */
  protected function occupancy(): ?OccupyCapableInterface {
    return $this->theme instanceof OccupyCapableInterface ? $this->theme : NULL;
  }

  /**
   * The theme, narrowed to the finished pieces the session draws around a form.
   *
   * @return \DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface
   *   The theme, able to draw them.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   */
  protected function pieces(): PrimitiveElementsInterface {
    if (!$this->theme instanceof PrimitiveElementsInterface) {
      $elements = PrimitiveElementsInterface::class;

      throw new \InvalidArgumentException(sprintf('%s cannot draw the session chrome: it does not implement %s.', $this->theme::class, $elements));
    }

    return $this->theme;
  }

  /**
   * The narrowest terminal the frame can be read in.
   *
   * Measured once, from the rows the form opens on: a minimum that followed the
   * answers would trip and clear again as they grew, which is a screen nobody
   * can work in rather than a guard.
   *
   * @param \DrevOps\Tui\Theme\Capability\OccupyCapableInterface $occupancy
   *   What the theme says about the terminal it takes.
   *
   * @return int
   *   The columns.
   */
  protected function narrowest(OccupyCapableInterface $occupancy): int {
    if ($this->minimumWidth !== NULL) {
      return $this->minimumWidth;
    }

    $stated = $occupancy->minWidth();
    $needed = $stated > 0 ? $stated : $this->widest() + ($this->border === Border::None ? 0 : ScreenRenderer::CHROME);
    $widest = $occupancy->maxWidth();

    // A cap is a consumer's word that a narrower frame reads well enough, and
    // a guard asking for more than the cap allows could never be satisfied.
    return $this->minimumWidth = $widest > 0 ? min($needed, $widest) : $needed;
  }

  /**
   * The shortest terminal the frame can be read in.
   *
   * @param \DrevOps\Tui\Theme\Capability\OccupyCapableInterface $occupancy
   *   What the theme says about the terminal it takes.
   *
   * @return int
   *   The rows.
   */
  protected function shortest(OccupyCapableInterface $occupancy): int {
    $tallest = $occupancy->maxHeight();

    return $tallest > 0 ? min($occupancy->minHeight(), $tallest) : $occupancy->minHeight();
  }

  /**
   * The widest row the form draws, whatever room it is given to draw it in.
   *
   * @return int
   *   The columns.
   */
  protected function widest(): int {
    $width = 0;

    foreach (Tree::panels($this->panel) as $panel) {
      foreach ($panel->blocks() as $block) {
        // A panel is measured by what it holds rather than by the row it draws,
        // and an entered one draws no row at all.
        if ($block instanceof Panel) {
          continue;
        }

        if ($block instanceof DependCapableInterface && $block->isHidden()) {
          continue;
        }

        $width = max($width, Ansi::blockWidth(explode("\n", $block->render($this->theme))));
      }
    }

    return $width;
  }

  /**
   * Put the way out into each panel that has one of its own.
   *
   * @param \DrevOps\Tui\Screen\Assembler $assembler
   *   The assembler that builds the standard pair.
   */
  protected function dress(Assembler $assembler): void {
    // The buttons end the form rather than the panel the cursor happens to be
    // in, so they go among the outermost panel's own rows: going into a nested
    // one leaves them behind exactly as it leaves that panel's siblings behind.
    if ($this->panel->currentButtons()->show && !$this->carries($this->panel)) {
      $this->panel->place()->add($this->notice)->add($this->actions);
    }

    foreach (Tree::panels($this->panel) as $panel) {
      // The outermost panel is not somewhere you opened, so it has nothing of
      // its own to close: the buttons in it are the form's.
      if ($panel === $this->panel) {
        continue;
      }
      if (!$panel->isModal()) {
        continue;
      }
      if ($this->carries($panel)) {
        continue;
      }

      $region = $panel->place();

      // A dialog that only says something says it here: its standing text is
      // its whole content, where a panel you walk into has rows instead.
      if ($panel->descriptionText() !== '') {
        $region->prepend(new Markup($panel->id() . '-description', $panel->descriptionText()));
      }

      $region->add($this->buttons($assembler, $panel));
    }

    foreach (Tree::fields($this->panel) as $field) {
      $field->handoff($this->externalEditor->isAvailable());
      $field->reuse(...$this->collector->reusable($field->id()));
    }
  }

  /**
   * The buttons that close a panel, labelled as it declares them.
   *
   * @param \DrevOps\Tui\Screen\Assembler $assembler
   *   The assembler that builds the standard pair.
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel they close.
   *
   * @return \DrevOps\Tui\Block\Actions
   *   The buttons.
   */
  protected function buttons(Assembler $assembler, Panel $panel): Actions {
    $declared = $panel->currentButtons();

    // The assembler says which buttons a form has; the panel says what they
    // read, so a form that renamed its submit gets the name it chose.
    return ($this->placed($panel) ?? $assembler->actions())
      ->action(Ending::Submit->value, Translator::t($declared->submitLabel))
      ->action(Ending::Cancel->value, Translator::t($declared->cancelLabel));
  }

  /**
   * The way out a panel is already carrying, if it has been given one.
   *
   * One declaration outlives the session driving it, so a second run over the
   * same panels takes on the buttons that are there rather than putting a
   * second pair beside them.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return \DrevOps\Tui\Block\Actions|null
   *   The buttons, or NULL when the panel carries none.
   */
  protected function placed(Panel $panel): ?Actions {
    foreach ($panel->place()->blocks() as $block) {
      if ($block instanceof Actions) {
        return $block;
      }
    }

    return NULL;
  }

  /**
   * The row a panel is already carrying for what withholds its end.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return \DrevOps\Tui\Block\Markup|null
   *   The row, or NULL when the panel carries none.
   */
  protected function stated(Panel $panel): ?Markup {
    foreach ($panel->place()->blocks() as $block) {
      if ($block instanceof Markup && $block->id() === self::NOTICE) {
        return $block;
      }
    }

    return NULL;
  }

  /**
   * Whether a panel already carries the way out of it.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   *
   * @return bool
   *   TRUE when it does.
   */
  protected function carries(Panel $panel): bool {
    return $this->placed($panel) instanceof Actions;
  }

  /**
   * The screen a field's help is drawn on.
   *
   * The trail and the keys on offer are the same blocks the panel's own screen
   * draws, so asking for help changes what is being read and nothing else.
   *
   * @return \DrevOps\Tui\Screen\Screen
   *   The screen.
   */
  protected function helpScreen(): Screen {
    // Help always reads as trail, card, keys - whatever arrangement the
    // session behind it uses.
    $screen = (new Screen())->layout(new DefaultLayout());

    $screen->in('header')->add($this->breadcrumb);
    $screen->in(self::CONTENT)->add($this->help);
    $screen->in('footer')->add($this->legend);

    return $screen;
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
  protected function furniture(string $name, string $kind): ?object {
    if (!in_array($name, $this->screen->currentLayout()->names(), TRUE)) {
      return NULL;
    }

    foreach ($this->screen->in($name)->blocks() as $block) {
      if ($block instanceof $kind) {
        return $block;
      }
    }

    // The assembler puts one of each into every region it furnishes, so a
    // furnished region missing its piece never reaches a frame.
    // @codeCoverageIgnoreStart
    throw new \LogicException(sprintf('The assembled screen holds no %s in its "%s" region.', $kind, $name));
    // @codeCoverageIgnoreEnd
  }

}
