<?php

declare(strict_types=1);

namespace DrevOps\Tui;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Block\BlockInterface;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface;
use DrevOps\Tui\Primitive\Output;
use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Resolver\InputResolver;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaGenerator;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\Layout\LayoutManager;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Terminal\Terminal;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Capability\OccupyCapableInterface;
use DrevOps\Tui\Theme\Capability\OverrideCapableInterface;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\Override\Overrides;
use DrevOps\Tui\Theme\ThemeBuilder;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Theme\ThemeManager;
use DrevOps\Tui\Translation\Translator;

/**
 * The one-class entry point for collecting a form's answers.
 *
 * Wraps the collector, input resolver, schema tools and panel TUI so a consumer
 * can collect answers - headlessly or interactively - in a single call. It also
 * owns the global TUI runtime shared by every form: the theme, key bindings,
 * colour and glyph forcing, the key-hint footer, screen clearing and the active
 * language, each set through a fluent setter. Those internals stay reachable
 * via root() and registry() when a consumer wants finer control.
 *
 * @package DrevOps\Tui
 */
final class Tui {

  /**
   * The handler registry.
   */
  protected HandlerRegistry $registry;

  /**
   * The effective env-variable prefix for per-question overrides.
   */
  protected string $envPrefix;

  /**
   * The headless collector, created on first use.
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
   * The consumer's overrides, applied to whichever theme is selected.
   */
  protected ?Overrides $themeOverrides = NULL;

  /**
   * The layout the screen is arranged by.
   */
  protected string $layout = 'default';

  /**
   * The blocks put in the screen's own regions, in the order they were placed.
   *
   * @var list<array{region:string,block:\DrevOps\Tui\Block\BlockInterface,tail:bool}>
   */
  protected array $placements = [];

  /**
   * The direction each named region runs its blocks, where one was stated.
   *
   * @var array<string,\DrevOps\Tui\Screen\Axis>
   */
  protected array $flows = [];

  /**
   * The resolved key bindings; NULL uses the default preset.
   */
  protected ?KeyMap $keyMap = NULL;

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
   * @param \DrevOps\Tui\Builder\Form $form
   *   The form declaring the panels and fields to collect.
   * @param string[] $handler_namespaces
   *   Namespaces searched, in order, for per-field consumer classes offering
   *   reusable static validate()/transform() behaviour.
   * @param string $env_prefix
   *   The env-variable prefix for per-question overrides; wins over the
   *   form-declared prefix, which wins over the "TUI_" default.
   */
  public function __construct(protected Form $form, array $handler_namespaces = [], string $env_prefix = '') {
    $declared = $form->currentEnvPrefix();
    $this->envPrefix = $env_prefix !== '' ? $env_prefix : ($declared !== '' ? $declared : 'TUI_');
    $this->registry = new HandlerRegistry($handler_namespaces);
  }

  /**
   * Select the theme, or state what it draws differently.
   *
   * A name picks the theme and its display options. A closure receives a
   * {@see \DrevOps\Tui\Theme\ThemeBuilder} and states the elements the
   * selected theme draws differently; an element it does not name keeps the
   * theme's own value, so the closure is a patch rather than a replacement.
   * A palette change suits a theme subclass; a handful of glyphs suits the
   * closure.
   *
   * @code
   * $tui->theme('mono')
   *   ->theme(fn(ThemeBuilder $t) => $t
   *     ->breadcrumb(fn(BreadcrumbOverrides $b) => $b->separator('›', '>'))
   *     ->field(fn(FieldOverrides $f) => $f->selector('❯', '>')));
   * @endcode
   *
   * @param string|\Closure $theme
   *   The theme name or class - empty (or "auto") auto-detects light/dark from
   *   the terminal background - or an `fn (ThemeBuilder $t): void` stating what
   *   the selected theme draws differently.
   * @param array<string,mixed> $options
   *   Display options for the theme, keyed by name - e.g.
   *   `['spacing' => Spacing::Padded, 'border' => Border::Rounded]` - plus any
   *   a custom theme reads. Ignored when a closure is given, which patches the
   *   theme rather than choosing one.
   *
   * @return $this
   *   The facade.
   */
  public function theme(string|\Closure $theme, array $options = []): self {
    if ($theme instanceof \Closure) {
      $builder = new ThemeBuilder();
      $theme($builder);
      $this->themeOverrides = $builder->overrides();

      return $this;
    }

    $this->theme = $theme;
    $this->themeOptions = $options;

    return $this;
  }

  /**
   * Build the selected theme and apply the consumer's overrides.
   *
   * @param string $name
   *   The theme name or class; empty falls back to the facade's theme.
   * @param int $width
   *   The frame width.
   * @param array<string,mixed> $options
   *   The resolved display options.
   *
   * @return \DrevOps\Tui\Theme\ThemeInterface
   *   The theme.
   */
  protected function buildTheme(string $name, int $width, array $options): ThemeInterface {
    $theme = ThemeManager::create($this->resolveTheme($name), $width, $options);

    // Overrides apply only through a theme's own override capability, so a
    // theme without it is used unchanged rather than rejected.
    if (!$this->themeOverrides instanceof Overrides || !$theme instanceof OverrideCapableInterface) {
      return $theme;
    }

    return $theme->overrides($this->themeOverrides);
  }

  /**
   * Set the layout the interactive screen is arranged by.
   *
   * The name resolves through the layout manager - a shipped layout, one
   * registered by the consumer, or a class - and an unknown name throws here
   * rather than mid-session. Headless collection is unaffected, since a layout
   * exists only to arrange drawing.
   *
   * @param string $layout
   *   The layout name or class. Empty selects the default layout.
   *
   * @return $this
   *   The facade.
   */
  public function layout(string $layout): self {
    $this->layout = $layout === '' ? 'default' : $layout;
    $this->assertRegions();

    return $this;
  }

  /**
   * Put a consumer block in one of the screen's regions.
   *
   * The regions around the form belong to the session rather than the form,
   * so their extra blocks are placed here rather than declared on a panel: a
   * note beside the trail, a version string beside the key hints. The built-in
   * blocks are placed first, so a placed block follows the trail or the hints
   * its region already holds.
   *
   * The $tail flag selects which end of the region's run the block packs
   * from. Packing from the end puts a block against the far edge - the right
   * of a region running across, the last row of one running down - and where
   * the two runs meet, a block packed from the start keeps its space.
   *
   * @code
   * $tui->layout('market')
   *   ->place('header', new Markup('preview', '(read-only preview)'))
   *   ->place('footer', new Markup('version', 'v1.2.3'), tail: TRUE)
   *   ->flow('footer', Axis::Columns);
   * @endcode
   *
   * @param string $region
   *   The region name, as the layout declares it.
   * @param \DrevOps\Tui\Block\BlockInterface $block
   *   The block.
   * @param bool $tail
   *   Whether it packs from the end of the region's run rather than the start.
   *
   * @return $this
   *   The facade.
   */
  public function place(string $region, BlockInterface $block, bool $tail = FALSE): self {
    $this->placements[] = ['region' => $region, 'block' => $block, 'tail' => $tail];
    $this->assertRegions();

    return $this;
  }

  /**
   * Run one region's blocks across it rather than down it.
   *
   * A region the layout declared running one way is turned the other way
   * here, so a form needs no layout of its own every time two blocks belong
   * on the same line. A region running across gives each block the width it
   * drew; one running down gives each a row. On either axis, a region that
   * was not declared to scroll clips what overflows it.
   *
   * @param string $region
   *   The region name, as the layout declares it.
   * @param \DrevOps\Tui\Screen\Axis $axis
   *   The direction its blocks run.
   *
   * @return $this
   *   The facade.
   */
  public function flow(string $region, Axis $axis): self {
    $this->flows[$region] = $axis;
    $this->assertRegions();

    return $this;
  }

  /**
   * Check that the layout declares every region name stated so far.
   *
   * Every setter that can invalidate the check re-runs it, so an unknown name
   * throws at the call that stated it, whichever order the layout and the
   * regions were named in.
   *
   * @throws \InvalidArgumentException
   *   When the layout declares no region of a stated name.
   */
  protected function assertRegions(): void {
    $arranged = LayoutManager::create($this->layout);

    foreach ([...array_column($this->placements, 'region'), ...array_keys($this->flows)] as $name) {
      $arranged->in($name);
    }
  }

  /**
   * Set the key-binding preset and optional overrides.
   *
   * The preset names the base bindings ("default", "vim", a registered name, or
   * a preset class); each override is a {@see \DrevOps\Tui\Input\Binding}
   * naming a scope, an action and its keys, applied on top of the preset.
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
    $this->keyMap = KeyMapManager::create($preset, $overrides);

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
   *   Whether to enable discovery against an existing project. Discovery
   *   feeds the chosen mode's initial state: the panels open pre-filled
   *   interactively, and the headless answers resolve to the same values.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\CollectException
   *   When the answers cannot be taken as they were given.
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
    $root = $this->root();
    $inputs = (new InputResolver($this->envPrefix))->resolve(Tree::fields($root), $prompts, getenv());

    $this->collector ??= new Collector($this->registry, $this->form->currentFixups());

    return $this->collector->answers($root, $inputs, $this->context($directory, $update, $version));
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
   * @param \DrevOps\Tui\Terminal\Terminal|null $terminal
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

    $theme = $this->buildTheme('', ThemeInterface::DEFAULT_WIDTH, $this->primitiveThemeOptions());

    return (new Progress($terminal, self::pieces($theme), $terminal->isOutputTty(), $total, $caption))->run($work);
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
   * @param \DrevOps\Tui\Terminal\Terminal|null $terminal
   *   The terminal to write to (defaults to a real one on standard error).
   *
   * @return \DrevOps\Tui\Primitive\Output
   *   The output primitive; one instance writes any number of pieces.
   */
  public function output(?Terminal $terminal = NULL): Output {
    // Restore this facade's language at the operation boundary (see collect()).
    Translator::setShared($this->translator);

    $terminal ??= self::primitiveTerminal();

    $options = $this->primitiveThemeOptions($terminal->isOutputTty());
    $theme = $this->buildTheme('', self::frameWidth($options, $terminal->columns()), $options);

    return new Output($terminal, self::pieces($theme));
  }

  /**
   * The theme, narrowed to the finished pieces a primitive writes.
   *
   * A card, a grid, a status line and a bar are composed rather than styled,
   * so a theme that implements none of them fails here with a named error
   * rather than at the first line it writes.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return \DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface
   *   The theme, able to draw them.
   *
   * @throws \InvalidArgumentException
   *   When the theme does not implement the elements.
   */
  protected static function pieces(ThemeInterface $theme): PrimitiveElementsInterface {
    if (!$theme instanceof PrimitiveElementsInterface) {
      $elements = PrimitiveElementsInterface::class;

      throw new \InvalidArgumentException(sprintf('%s cannot draw a primitive: it does not implement %s.', $theme::class, $elements));
    }

    return $theme;
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
   * @param \DrevOps\Tui\Terminal\Terminal|null $terminal
   *   The terminal to drive (defaults to a real one).
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\CollectException
   *   When the answers cannot be taken as they were given.
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

    $options = $this->resolveThemeOptions($terminal);

    return $this->controller($options, $theme, $banner, $version, $directory, self::frameWidth($options, $terminal->columns()), $update)->run($terminal);
  }

  /**
   * Build the session that drives the form for the resolved display options.
   *
   * Builds the tree the form declares, resolves the theme and banner and
   * wires the session, so a caller that supplies its own terminal (a real
   * one, or a scripted one for tests) can run the session against it.
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
  public function controller(array $options, string $theme = '', string $banner = '', string $version = '', string $directory = '', int $width = ThemeInterface::DEFAULT_WIDTH, bool $update = FALSE): ScreenController {
    // Restore this facade's language before rendering (see collect()).
    Translator::setShared($this->translator);

    $drawn = $this->buildTheme($theme, $width, $options);

    return new ScreenController(
      $this->root(),
      $drawn,
      [],
      $this->keyMap ?? KeyMapManager::create(),
      new Collector($this->registry, $this->form->currentFixups()),
      $this->context($directory, $update, $version),
      // The border must match the frame the theme laid its rows out to, so it
      // is read back off the built theme rather than resolved a second time.
      // A theme without the occupy capability gets no border.
      layout: $this->layout,
      border: $drawn instanceof OccupyCapableInterface ? $drawn->borderStyle() : Border::None,
      clearOnExit: $this->clearOnExit,
      footer: $this->footer,
      banner: $banner !== '' ? $banner : $this->form->currentBanner(),
      version: $version,
      placements: $this->placements,
      flows: $this->flows,
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

    return $terminal_width > 0 ? min(ThemeInterface::DEFAULT_WIDTH, $terminal_width) : ThemeInterface::DEFAULT_WIDTH;
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
    return (new AgentHelp($this->root(), $context ?? new Context(), $this->envPrefix))->generate();
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
   * The handler registry.
   *
   * @return \DrevOps\Tui\Handler\HandlerRegistry
   *   The handler registry.
   */
  public function registry(): HandlerRegistry {
    return $this->registry;
  }

  /**
   * The declared block tree: the panel every declared panel hangs from.
   *
   * The rows a form asks about are state: a set of entries supplied from
   * elsewhere is stored on the block holding it, so one tree carries the
   * declaration and its state. Every operation on this facade reads that one
   * tree.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The root panel.
   */
  public function root(): Panel {
    return $this->form->root();
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
   * @param \DrevOps\Tui\Terminal\Terminal $terminal
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
   * A primitive is chrome, not data, so it writes to standard error and
   * leaves standard output to a consumer's own results.
   *
   * @return \DrevOps\Tui\Terminal\Terminal
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
