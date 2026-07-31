<?php

declare(strict_types=1);

namespace DrevOps\Tui;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Block\BlockFactory;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Primitive\Output;
use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Resolver\InputResolver;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaGenerator;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Translation\Translator;

/**
 * The one-class entry point for collecting a form's answers.
 *
 * Wraps the engine, input resolver, schema tools and panel TUI so a consumer
 * can collect answers - headlessly or interactively - in a single call. It also
 * owns the global TUI runtime shared by every form: the theme, key bindings,
 * colour and glyph forcing, the key-hint footer, screen clearing and the active
 * language, each set through a fluent setter. Those internals stay reachable
 * via form(), engine() and registry() when a consumer wants finer control.
 *
 * @package DrevOps\Tui
 */
final class Tui {

  /**
   * The handler registry.
   */
  protected HandlerRegistry $registry;

  /**
   * The engine.
   */
  protected Engine $engine;

  /**
   * The effective env-variable prefix for per-question overrides.
   */
  protected string $envPrefix;

  /**
   * The form definition.
   */
  protected FormDefinition $form;

  /**
   * The declared block tree, once the answer-side operations have needed one.
   */
  protected ?Panel $root = NULL;

  /**
   * What collects the answers with no screen at all, once one is asked for.
   */
  protected ?Collector $collector = NULL;

  /**
   * The theme name or class (empty for the default).
   */
  protected string $theme = '';

  /**
   * Display options passed to the interactive theme.
   *
   * @var array<string,mixed>
   */
  protected array $themeOptions = [];

  /**
   * The resolved key bindings; NULL uses the default preset.
   */
  protected ?KeyMap $keymap = NULL;

  /**
   * Force ANSI colour on/off; NULL auto-detects.
   */
  protected ?bool $color = NULL;

  /**
   * Force Unicode/ASCII glyphs; NULL auto-detects.
   */
  protected ?bool $unicode = NULL;

  /**
   * Whether descriptions and notes render their markdown; off by default.
   */
  protected bool $markdown = FALSE;

  /**
   * Expand the TUI to the whole terminal; NULL defers to the theme options.
   */
  protected ?bool $fullscreen = NULL;

  /**
   * Whether the interactive TUI shows the contextual key-hint footer.
   */
  protected bool $footer = TRUE;

  /**
   * Whether to clear the screen when the interactive TUI exits.
   */
  protected bool $clearOnExit = TRUE;

  /**
   * The translator localizing chrome and questions (NULL leaves English).
   */
  protected ?Translator $translator = NULL;

  /**
   * Construct a TUI.
   *
   * @param \DrevOps\Tui\Model\FormDefinition|\DrevOps\Tui\Builder\Form $form
   *   The form: a Form builder (built internally) or its built definition.
   * @param string[] $handler_namespaces
   *   Namespaces searched, in order, for per-field consumer classes offering
   *   reusable static validate()/transform() behaviour.
   * @param string $env_prefix
   *   The env-variable prefix for per-question overrides; wins over the
   *   form-declared prefix, which wins over the "TUI_" default.
   */
  public function __construct(FormDefinition|Form $form, array $handler_namespaces = [], string $env_prefix = '') {
    $this->form = $form instanceof Form ? $form->build() : $form;
    $this->envPrefix = $env_prefix !== '' ? $env_prefix : ($this->form->envPrefix !== '' ? $this->form->envPrefix : 'TUI_');
    $this->registry = new HandlerRegistry($handler_namespaces);
    $this->engine = new Engine($this->form, $this->registry);
  }

  /**
   * Set the interactive theme name and its display options.
   *
   * @param string $theme
   *   The theme name or class. Empty (or "auto") auto-detects light/dark from
   *   the terminal background.
   * @param array<string,mixed> $options
   *   Display options for the theme, keyed by name - e.g.
   *   `['spacing' => Spacing::Padded, 'border' => Border::Rounded]` - plus any
   *   a custom theme reads.
   *
   * @return $this
   *   The facade.
   */
  public function theme(string $theme, array $options = []): self {
    $this->theme = $theme;
    $this->themeOptions = $options;

    return $this;
  }

  /**
   * Set the key-binding preset and optional overrides.
   *
   * The preset names the base bindings ("default", "vim", a registered name, or
   * a preset class); each override is a {@see \DrevOps\Tui\Input\Binding}
   * naming a scope, an action and its keys, retuning it on top of the preset.
   * Conflicting, un-typeable or malformed bindings throw here, not mid-session.
   *
   * @param string $preset
   *   The preset name or class. Empty selects the default preset.
   * @param list<\DrevOps\Tui\Input\Binding> $overrides
   *   Bindings applied on top of the preset.
   *
   * @return $this
   *   The facade.
   */
  public function keys(string $preset = '', array $overrides = []): self {
    $this->keymap = KeyMapManager::create($preset, $overrides);

    return $this;
  }

  /**
   * Force ANSI colour on or off.
   *
   * @param bool|null $color
   *   TRUE/FALSE to force, NULL to auto-detect.
   *
   * @return $this
   *   The facade.
   */
  public function color(?bool $color): self {
    $this->color = $color;

    return $this;
  }

  /**
   * Force Unicode or ASCII glyphs.
   *
   * @param bool|null $unicode
   *   TRUE/FALSE to force, NULL to auto-detect.
   *
   * @return $this
   *   The facade.
   */
  public function unicode(?bool $unicode): self {
    $this->unicode = $unicode;

    return $this;
  }

  /**
   * Render a lightweight markdown subset in field descriptions and notes.
   *
   * With this on, description and note text carries `**bold**`, `*emphasis*`,
   * `` `code` ``, `[text](url)` links and `- ` bullet lists, mapped to the
   * theme's style atoms. Links resolve either way; this only enables the rest
   * of the subset. Headless collection and incapable terminals fall back to
   * clean plain text.
   *
   * @param bool $markdown
   *   Whether markdown is rendered.
   *
   * @return $this
   *   The facade.
   */
  public function markdown(bool $markdown = TRUE): self {
    $this->markdown = $markdown;

    return $this;
  }

  /**
   * Expand the interactive TUI to the whole terminal screen.
   *
   * Sugar for the "fullscreen" theme option: the frame stretches to the
   * terminal, bounded by the "max_width"/"max_height" options, and the content
   * anchors to the "halign"/"valign" alignments. Below the "min_width" (by
   * default measured from the content) or "min_height" options the TUI shows a
   * resize notice instead of a broken layout. Headless collection is
   * unaffected.
   *
   * @param bool $fullscreen
   *   Whether to expand to the whole terminal.
   *
   * @return $this
   *   The facade.
   */
  public function fullscreen(bool $fullscreen = TRUE): self {
    $this->fullscreen = $fullscreen;

    return $this;
  }

  /**
   * Set whether the contextual key-hint footer is shown.
   *
   * @param bool $show
   *   Whether to show the footer.
   *
   * @return $this
   *   The facade.
   */
  public function footer(bool $show): self {
    $this->footer = $show;

    return $this;
  }

  /**
   * Set whether to clear the screen when the TUI exits.
   *
   * @param bool $clear
   *   Whether to clear on exit.
   *
   * @return $this
   *   The facade.
   */
  public function clearOnExit(bool $clear): self {
    $this->clearOnExit = $clear;

    return $this;
  }

  /**
   * Set the translator localizing chrome and questions.
   *
   * The translator carries the active language and catalog sources and is
   * activated process-wide so `t()` resolves during a run. Without one, every
   * string renders in its English source.
   *
   * @param \DrevOps\Tui\Translation\Translator $translator
   *   The translator.
   *
   * @return $this
   *   The facade.
   */
  public function translator(Translator $translator): self {
    $this->translator = $translator;

    return $this;
  }

  /**
   * Collect answers, interactively on a terminal or headlessly otherwise.
   *
   * Routes to interact() when no prompts are supplied and standard input is a
   * TTY, and to collect() otherwise. Pass $interactive to force a mode - for
   * example from a console framework's own interactivity detection.
   *
   * @param string $prompts
   *   Answers as a JSON string (or a path to a JSON file), empty for none.
   * @param string $version
   *   The version stamped into the context (and shown below the banner).
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param bool|null $interactive
   *   TRUE/FALSE to force the mode; NULL auto-detects from the prompts and
   *   the standard-input TTY.
   * @param bool $update
   *   Whether to enable discovery against an existing project. It reaches the
   *   chosen mode's initial state, so the panels open pre-filled interactively
   *   just as the headless answers resolve them.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When the engine cannot process the configuration or answers.
   * @throws \DrevOps\Tui\InterruptException
   *   When the user aborts the interactive session with the interrupt key.
   * @throws \DrevOps\Tui\CancelException
   *   When the user dismisses the interactive session via the cancel button.
   */
  public function run(string $prompts = '', string $version = '', string $directory = '', ?bool $interactive = NULL, bool $update = FALSE): Answers {
    $interactive ??= $prompts === '' && defined('STDIN') && stream_isatty(STDIN);

    return $interactive ? $this->interact(version: $version, directory: $directory, update: $update) : $this->collect($prompts, $directory, $update, $version);
  }

  /**
   * Collect answers non-interactively from a JSON payload and the environment.
   *
   * @param string $prompts
   *   Answers as a JSON string (or empty to rely on defaults and environment).
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param bool $update
   *   Whether to enable discovery against an existing project.
   * @param string $version
   *   The version stamped into the context.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   */
  public function collect(string $prompts = '', string $directory = '', bool $update = FALSE, string $version = ''): Answers {
    // Restore this facade's language at the operation boundary: another facade
    // constructed or configured meanwhile may have replaced the shared one.
    Translator::setShared($this->translator);
    $inputs = (new InputResolver($this->envPrefix))->resolve($this->form->fields(), $prompts, getenv());

    $this->collector ??= new Collector($this->registry, $this->form->fixups);

    return $this->collector->answers($this->root(), $inputs, $this->context($directory, $update, $version));
  }

  /**
   * Show a progress indicator while a slow callback runs.
   *
   * The callback receives the {@see \DrevOps\Tui\Primitive\Progress} and drives
   * it with `advance()`; its return value is passed straight back. With no
   * total the indicator is an animated spinner - each advance ticks a frame;
   * with a total it is a bar that fills as it advances, with a step count and
   * label. The active theme draws it, so it matches the panel's look and
   * honours the colour and Unicode switches. On an interactive terminal it
   * animates and settles when the callback returns; off a TTY it prints the
   * caption once as a plain line and emits no control sequences.
   *
   * @param int|null $total
   *   The number of steps for a determinate bar, or NULL for an indeterminate
   *   spinner.
   * @param string $caption
   *   The caption shown beside the indicator.
   * @param callable(\DrevOps\Tui\Primitive\Progress): TReturn $work
   *   The work to run; it receives the progress primitive and its result is
   *   returned.
   * @param \DrevOps\Tui\Render\Terminal|null $terminal
   *   The terminal to draw on (defaults to a real one on standard error).
   *
   * @return TReturn
   *   The callback's return value.
   *
   * @template TReturn
   */
  public function progress(?int $total, string $caption, callable $work, ?Terminal $terminal = NULL): mixed {
    // Restore this facade's language at the operation boundary (see collect()).
    Translator::setShared($this->translator);

    $terminal ??= self::primitiveTerminal();

    $theme = ThemeManager::create($this->resolveTheme(''), DefaultTheme::DEFAULT_WIDTH, $this->primitiveThemeOptions());

    return (new Progress($terminal, $theme, $terminal->isOutputTty(), $total, $caption))->run($work);
  }

  /**
   * The output primitives for the chrome around a form run.
   *
   * Boxes, status lines and definition lists a consumer writes before, after or
   * between form runs - a welcome box, a "ready" summary, the next steps. The
   * active theme draws them, so they match the panel's look and honour the
   * colour and Unicode switches, and they wrap to the terminal's width. Off an
   * interactive terminal the colour is dropped unless it was forced, so a
   * captured log holds clean text.
   *
   * @param \DrevOps\Tui\Render\Terminal|null $terminal
   *   The terminal to write to (defaults to a real one on standard error).
   *
   * @return \DrevOps\Tui\Primitive\Output
   *   The output primitive; hold onto it to write more than one piece.
   */
  public function output(?Terminal $terminal = NULL): Output {
    // Restore this facade's language at the operation boundary (see collect()).
    Translator::setShared($this->translator);

    $terminal ??= self::primitiveTerminal();

    $options = $this->primitiveThemeOptions($terminal->isOutputTty());
    $theme = ThemeManager::create($this->resolveTheme(''), self::frameWidth($options, $terminal->width()), $options);

    return new Output($terminal, $theme);
  }

  /**
   * Collect answers interactively through the panel TUI.
   *
   * @param string $theme
   *   The theme name or class. Empty falls back to the facade's theme; an empty
   *   facade theme (or "auto") auto-detects light/dark from the terminal
   *   background.
   * @param string $banner
   *   An optional start banner.
   * @param string $version
   *   An optional version shown below the banner and stamped into the context.
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param bool $update
   *   Whether discovery pre-fills the panels from an existing project, with the
   *   detected values carrying their `detected` provenance badge.
   * @param \DrevOps\Tui\Render\Terminal|null $terminal
   *   The terminal to drive (defaults to a real one).
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When the engine cannot process the configuration or answers.
   * @throws \DrevOps\Tui\InterruptException
   *   When the user aborts the interactive session with the interrupt key.
   * @throws \DrevOps\Tui\CancelException
   *   When the user dismisses the interactive session via the cancel button.
   */
  public function interact(string $theme = '', string $banner = '', string $version = '', string $directory = '', bool $update = FALSE, ?Terminal $terminal = NULL): Answers {
    if (!$terminal instanceof Terminal) {
      // @codeCoverageIgnoreStart
      $terminal = new Terminal();
      // @codeCoverageIgnoreEnd
    }

    // The theme's display options (colour, Unicode, mode) come from the facade
    // when set, otherwise they are auto-detected from the terminal.
    $options = $this->resolveThemeOptions($terminal);

    return $this->controller($options, $theme, $banner, $version, $directory, self::frameWidth($options, $terminal->width()), $update)->run($terminal);
  }

  /**
   * Build the session that drives the form for the resolved display options.
   *
   * Shared by interact() and the test harness: it builds the tree the form
   * declares, resolves the theme and banner and wires the session - so a caller
   * that supplies its own terminal (a real one, or a scripted one for tests)
   * can run it against that.
   *
   * @param array<string,mixed> $options
   *   The resolved theme display options (colour, Unicode, mode).
   * @param string $theme
   *   The theme name or class; empty falls back to the facade's theme.
   * @param string $banner
   *   An optional start banner; empty falls back to the form's banner.
   * @param string $version
   *   An optional version shown below the banner and stamped into the context.
   * @param string $directory
   *   The target directory (defaults to the current working directory).
   * @param int $width
   *   The frame width the theme lays out to (the terminal width when
   *   fullscreen is on).
   * @param bool $update
   *   Whether discovery pre-fills the initial state from an existing project.
   *
   * @return \DrevOps\Tui\Screen\ScreenController
   *   The session, ready to run against a terminal.
   *
   * @internal
   *   Public for the {@see \DrevOps\Tui\Testing\TuiTester} harness; consumers
   *   collect through run(), collect() or interact().
   */
  public function controller(array $options, string $theme = '', string $banner = '', string $version = '', string $directory = '', int $width = DefaultTheme::DEFAULT_WIDTH, bool $update = FALSE): ScreenController {
    // Restore this facade's language before rendering (see collect()).
    Translator::setShared($this->translator);

    $drawn = ThemeManager::create($this->resolveTheme($theme), $width, $options);

    return new ScreenController(
      // A session is typed into, so it gets a tree of its own: the answer-side
      // operations read one nobody has moved a cursor through.
      (new BlockFactory())->create($this->form),
      $drawn,
      [],
      $this->keymap ?? KeyMapManager::create(),
      new Collector($this->registry, $this->form->fixups),
      $this->context($directory, $update, $version),
      // The frame the theme was told to lay its rows out to is the frame that
      // has to be drawn around them, so the border is read back off it rather
      // than resolved a second time here.
      border: $drawn->borderStyle(),
      clearOnExit: $this->clearOnExit,
      footer: $this->footer,
      banner: $banner !== '' ? $banner : $this->form->banner,
      version: $version,
    );
  }

  /**
   * The frame width the theme lays out to for the resolved options.
   *
   * A fullscreen frame lays out to the terminal's width (the theme caps it
   * with "max_width"); the width is fixed for the session, like the theme.
   * Anything else lays out to the default width, clamped to the terminal:
   * rows and right-aligned badges sized past the terminal hard-wrap onto
   * the next line and corrupt the whole layout below them.
   *
   * @param array<string,mixed> $options
   *   The resolved theme display options.
   * @param int $terminal_width
   *   The terminal's width in columns.
   *
   * @return int
   *   The frame width.
   */
  public static function frameWidth(array $options, int $terminal_width): int {
    if (($options['fullscreen'] ?? FALSE) === TRUE) {
      return $terminal_width;
    }

    return $terminal_width > 0 ? min(DefaultTheme::DEFAULT_WIDTH, $terminal_width) : DefaultTheme::DEFAULT_WIDTH;
  }

  /**
   * The JSON schema describing the questions.
   *
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The context a closure default is evaluated against; NULL uses an empty
   *   context carrying no prior answers.
   *
   * @return array<string,mixed>
   *   The schema.
   */
  public function schema(?Context $context = NULL): array {
    return (new SchemaGenerator($this->root(), $context ?? new Context(), $this->envPrefix))->generate();
  }

  /**
   * Agent-facing help for driving the form non-interactively.
   *
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The context a closure default is evaluated against; NULL uses an empty
   *   context carrying no prior answers.
   *
   * @return string
   *   The help text.
   */
  public function agentHelp(?Context $context = NULL): string {
    return (new AgentHelp($this->root(), $this->envPrefix, $context ?? new Context()))->generate();
  }

  /**
   * Validate an answer set against the schema.
   *
   * @param array<string,mixed> $answers
   *   The answers to validate.
   * @param \DrevOps\Tui\Handler\Context|null $context
   *   The context an options resolver is evaluated against, its answers
   *   replaced by the ones under validation; NULL uses an empty context.
   *
   * @return list<string>
   *   The validation errors (empty when valid).
   */
  public function validate(array $answers, ?Context $context = NULL): array {
    return (new SchemaValidator($this->root(), $context ?? new Context()))->validate($answers);
  }

  /**
   * The form definition.
   *
   * @return \DrevOps\Tui\Model\FormDefinition
   *   The form definition.
   */
  public function form(): FormDefinition {
    return $this->form;
  }

  /**
   * The engine.
   *
   * @return \DrevOps\Tui\Engine\Engine
   *   The engine.
   */
  public function engine(): Engine {
    return $this->engine;
  }

  /**
   * The handler registry.
   *
   * @return \DrevOps\Tui\Handler\HandlerRegistry
   *   The handler registry.
   */
  public function registry(): HandlerRegistry {
    return $this->registry;
  }

  /**
   * The declared block tree the answer-side operations read.
   *
   * The rows a form asks about are settled state - a set of entries that
   * arrives from somewhere else settles onto the block holding it - so the tree
   * is built once and kept, the way the interactive path keeps its engine.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel every declared panel hangs from.
   */
  protected function root(): Panel {
    return $this->root ??= (new BlockFactory())->create($this->form);
  }

  /**
   * Resolve the interactive theme name.
   *
   * The argument wins over the facade's theme; an empty result or the explicit
   * "auto" sentinel selects the default theme. The dark/light mode is a display
   * option resolved separately.
   *
   * @param string $theme
   *   The theme argument (empty to fall back to the facade's theme).
   *
   * @return string
   *   The resolved theme name.
   */
  protected function resolveTheme(string $theme): string {
    $name = $theme !== '' ? $theme : $this->theme;

    return $name === '' || $name === 'auto' ? 'default' : $name;
  }

  /**
   * Build the theme's display options, auto-detecting what the consumer omits.
   *
   * The facade's options win; anything unset for colour, Unicode and the
   * dark/light mode is filled from the detected terminal capabilities. The mode
   * follows the background only when colour is on - with colour off the palette
   * is invisible, so the background query is skipped.
   *
   * @param \DrevOps\Tui\Render\Terminal $terminal
   *   The terminal queried for its background during detection.
   *
   * @return array<string,mixed>
   *   The resolved options.
   */
  protected function resolveThemeOptions(Terminal $terminal): array {
    $options = $this->themeOptions;

    if (!isset($options['color'])) {
      $options['color'] = $this->resolvedColor();
    }

    if (!isset($options['unicode'])) {
      $options['unicode'] = $this->resolvedUnicode();
    }

    if (!isset($options['markdown'])) {
      $options['markdown'] = $this->markdown;
    }

    if (!isset($options['mode'])) {
      $options['mode'] = $options['color'] ? Terminal::detectMode($terminal->queryBackground()) : Mode::Dark;
    }

    if (!isset($options['fullscreen']) && $this->fullscreen !== NULL) {
      $options['fullscreen'] = $this->fullscreen;
    }

    return $options;
  }

  /**
   * The resolved colour switch: the forced value, else auto-detection.
   *
   * @return bool
   *   Whether colour is on.
   */
  protected function resolvedColor(): bool {
    return $this->color ?? Terminal::detectColor();
  }

  /**
   * The resolved Unicode switch: the forced value, else auto-detection.
   *
   * @return bool
   *   Whether Unicode glyphs are on.
   */
  protected function resolvedUnicode(): bool {
    return $this->unicode ?? Terminal::detectUnicode();
  }

  /**
   * A real terminal that draws a primitive's output on standard error.
   *
   * A primitive is chrome, not data, so it stays off standard output where a
   * consumer's own results are written.
   *
   * @return \DrevOps\Tui\Render\Terminal
   *   The terminal.
   */
  protected static function primitiveTerminal(): Terminal {
    // @codeCoverageIgnoreStart
    return new Terminal(defined('STDERR') ? STDERR : NULL);
    // @codeCoverageIgnoreEnd
  }

  /**
   * The theme display options for a primitive, filling what the consumer omits.
   *
   * Mirrors resolveThemeOptions() for colour and Unicode, but a primitive draws
   * a single line rather than a framed panel, so it skips the background query
   * and leaves the dark/light mode at dark unless the consumer set one.
   *
   * @param bool|null $tty
   *   Whether the primitive's output stream is an interactive terminal, or NULL
   *   to leave auto-detected colour alone. Escape codes written to a redirected
   *   stream land in the captured text rather than on a terminal, so an
   *   auto-detected colour switches off there; a forced one still wins.
   *
   * @return array<string,mixed>
   *   The resolved options.
   */
  protected function primitiveThemeOptions(?bool $tty = NULL): array {
    $options = $this->themeOptions;

    if (!isset($options['color'])) {
      // A forced colour wins over the stream: only auto-detection also requires
      // an interactive stream, where the escape codes have an effect at all.
      $options['color'] = $this->color ?? (Terminal::detectColor() && ($tty ?? TRUE));
    }

    if (!isset($options['unicode'])) {
      $options['unicode'] = $this->resolvedUnicode();
    }

    if (!isset($options['markdown'])) {
      $options['markdown'] = $this->markdown;
    }

    $options['mode'] ??= Mode::Dark;

    return $options;
  }

  /**
   * Build a run context for the target directory.
   *
   * @param string $directory
   *   The target directory (empty for the current working directory).
   * @param bool $update
   *   Whether discovery is enabled.
   * @param string $version
   *   The version stamped into the context.
   *
   * @return \DrevOps\Tui\Handler\Context
   *   The context.
   */
  protected function context(string $directory, bool $update, string $version): Context {
    return new Context($directory !== '' ? $directory : (string) getcwd(), [], $update, $version);
  }

}
