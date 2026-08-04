<?php

declare(strict_types=1);

namespace DrevOps\Tui\Testing;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Tui;

/**
 * Drives a screen from scripted keystrokes, and hands back what it drew.
 *
 * The screen-native companion to {@see \DrevOps\Tui\Testing\TuiTester}: it
 * feeds keystrokes through a scripted terminal's read() and runs the real
 * session loop, so a consumer can assert on the answers and on every frame that
 * reached the terminal - without a real TTY. Keystrokes are raw byte strings
 * (a typed word is one) and/or Key objects, which are encoded to the bytes a
 * terminal would emit for them.
 *
 * The display defaults are fixed rather than detected - no colour, glyphs on, a
 * dark palette, a terminal of a stated size - so a frame reads the same on
 * every machine that runs the test.
 *
 * @code
 * $tester = new ScreenTester($form->root());
 * $answers = $tester->run(Key::named(KeyName::Enter), 'Ada', Key::named(KeyName::Enter));
 * $this->assertSame('Ada', $answers->value('courier'));
 * @endcode
 *
 * @package DrevOps\Tui\Testing
 */
final class ScreenTester {

  /**
   * The message thrown when a result is read before run() was called.
   */
  protected const string NOT_RUN = 'Call run() before reading the results.';

  /**
   * The theme display options merged over the deterministic defaults.
   *
   * @var array<string,mixed>
   */
  protected array $options = ['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark];

  /**
   * The theme the blocks draw through, once one is given.
   */
  protected ?ThemeInterface $theme = NULL;

  /**
   * The bindings the screen answers to, once some are given.
   */
  protected ?KeyMap $keys = NULL;

  /**
   * What resolves the answers the form opens on, once one is given.
   */
  protected ?Collector $collector = NULL;

  /**
   * The run the session belongs to.
   */
  protected Context $context;

  /**
   * Values supplied for the fields, keyed by field id.
   *
   * @var array<string,mixed>
   */
  protected array $supplied = [];

  /**
   * The layout the screen is arranged by.
   */
  protected string $layout = 'default';

  /**
   * The frame drawn around every region at once.
   */
  protected Border $border = Border::None;

  /**
   * Whether the screen is cleared as the session ends.
   */
  protected bool $clearOnExit = TRUE;

  /**
   * Whether the keys that apply right now are advertised at all.
   */
  protected bool $footer = TRUE;

  /**
   * What is shown before the form, dismissed by any key.
   */
  protected string $banner = '';

  /**
   * The version shown under that banner.
   */
  protected string $version = '';

  /**
   * What hands a passage of text to an editor of the reader's own.
   */
  protected ?ExternalEditor $externalEditor = NULL;

  /**
   * The reported terminal height.
   */
  protected int $rows = 24;

  /**
   * The reported terminal width.
   */
  protected int $cols = 80;

  /**
   * The terminal the last run() drove, or NULL before the first run.
   */
  protected ?BufferedTerminal $terminal = NULL;

  /**
   * The answers the last run() collected, or NULL when it ended another way.
   */
  protected ?Answers $answers = NULL;

  /**
   * Construct a tester for a panel.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel the screen starts in: the tree a form declares.
   */
  public function __construct(protected Panel $panel) {
    $this->context = new Context();
  }

  /**
   * Set the theme the blocks draw through.
   *
   * @param \DrevOps\Tui\Theme\ThemeInterface $theme
   *   The theme.
   *
   * @return $this
   *   The tester.
   */
  public function theme(ThemeInterface $theme): self {
    $this->theme = $theme;

    return $this;
  }

  /**
   * Merge theme display options over the deterministic defaults.
   *
   * @param array<string,mixed> $options
   *   The options (e.g. "color", "unicode", "mode").
   *
   * @return $this
   *   The tester.
   */
  public function options(array $options): self {
    $this->options = $options + $this->options;

    return $this;
  }

  /**
   * Set the bindings the screen answers to.
   *
   * @param \DrevOps\Tui\Input\KeyMap $keys
   *   The bindings.
   *
   * @return $this
   *   The tester.
   */
  public function keys(KeyMap $keys): self {
    $this->keys = $keys;

    return $this;
  }

  /**
   * Set what resolves the answers the form opens on.
   *
   * @param \DrevOps\Tui\Screen\Collector $collector
   *   The collector, carrying whatever behaviour is reused across forms and
   *   whatever rules apply once the answers have settled.
   *
   * @return $this
   *   The tester.
   */
  public function collector(Collector $collector): self {
    $this->collector = $collector;

    return $this;
  }

  /**
   * Set the run the session belongs to.
   *
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context.
   *
   * @return $this
   *   The tester.
   */
  public function context(Context $context): self {
    $this->context = $context;

    return $this;
  }

  /**
   * Supply values for the fields, as a caller of the form would.
   *
   * @param array<string,mixed> $supplied
   *   The values, keyed by field id.
   *
   * @return $this
   *   The tester.
   */
  public function supplied(array $supplied): self {
    $this->supplied = $supplied;

    return $this;
  }

  /**
   * Set the layout the screen is arranged by.
   *
   * @param string $layout
   *   The layout name or class.
   *
   * @return $this
   *   The tester.
   */
  public function layout(string $layout): self {
    $this->layout = $layout;

    return $this;
  }

  /**
   * Set the frame drawn around every region at once.
   *
   * @param \DrevOps\Tui\Theme\Border $border
   *   The border.
   *
   * @return $this
   *   The tester.
   */
  public function border(Border $border): self {
    $this->border = $border;

    return $this;
  }

  /**
   * Set whether the screen is cleared as the session ends.
   *
   * @param bool $clear
   *   Whether it is cleared.
   *
   * @return $this
   *   The tester.
   */
  public function clearOnExit(bool $clear): self {
    $this->clearOnExit = $clear;

    return $this;
  }

  /**
   * Set whether the keys that apply right now are advertised at all.
   *
   * @param bool $show
   *   Whether they are.
   *
   * @return $this
   *   The tester.
   */
  public function footer(bool $show): self {
    $this->footer = $show;

    return $this;
  }

  /**
   * Set what is shown before the form, dismissed by any key.
   *
   * @param string $banner
   *   The banner.
   * @param string $version
   *   The version shown under it.
   *
   * @return $this
   *   The tester.
   */
  public function banner(string $banner, string $version = ''): self {
    $this->banner = $banner;
    $this->version = $version;

    return $this;
  }

  /**
   * Set what hands a passage of text to an editor of the reader's own.
   *
   * @param \DrevOps\Tui\Render\ExternalEditor $editor
   *   The editor service, so a test can answer for one without launching a
   *   program or depending on the environment naming one.
   *
   * @return $this
   *   The tester.
   */
  public function externalEditor(ExternalEditor $editor): self {
    $this->externalEditor = $editor;

    return $this;
  }

  /**
   * Set the reported terminal height.
   *
   * @param int $rows
   *   The number of rows.
   *
   * @return $this
   *   The tester.
   */
  public function rows(int $rows): self {
    $this->rows = $rows;

    return $this;
  }

  /**
   * Set the reported terminal width.
   *
   * @param int $cols
   *   The number of columns, at least 1.
   *
   * @return $this
   *   The tester.
   *
   * @throws \InvalidArgumentException
   *   When the width is below one column, which no frame can be laid out to.
   */
  public function cols(int $cols): self {
    if ($cols < 1) {
      throw new \InvalidArgumentException('The terminal width must be at least 1 column.');
    }

    $this->cols = $cols;

    return $this;
  }

  /**
   * Run the session, feeding it the given scripted keystrokes.
   *
   * @param string|\DrevOps\Tui\Input\Key ...$items
   *   The scripted input: each item is either raw keystroke bytes (a string,
   *   e.g. "\n" or "Ada") or a Key, encoded to its canonical bytes.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   *
   * @throws \DrevOps\Tui\InterruptException
   *   When the scripted keys aborted the session.
   * @throws \DrevOps\Tui\CancelException
   *   When the scripted keys abandoned the form.
   */
  public function run(string|Key ...$items): Answers {
    $keystrokes = [];

    foreach ($items as $item) {
      $keystrokes[] = $item instanceof Key ? KeyEncoder::encode($item) : $item;
    }

    $this->answers = NULL;
    $this->terminal = new BufferedTerminal($keystrokes, $this->rows, $this->cols);

    return $this->answers = $this->controller()->run($this->terminal);
  }

  /**
   * The answers the last run() collected.
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The answers.
   *
   * @throws \LogicException
   *   When run() has not been called, or ended without collecting any.
   */
  public function answers(): Answers {
    return $this->answers ?? throw new \LogicException(self::NOT_RUN);
  }

  /**
   * Everything the last run() wrote to the terminal.
   *
   * Readable after a session that ended by being abandoned or aborted, so what
   * was on screen at the time can still be asserted on.
   *
   * @return string
   *   The captured output, including ANSI escape sequences.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function output(): string {
    return $this->terminal instanceof BufferedTerminal ? $this->terminal->output() : throw new \LogicException(self::NOT_RUN);
  }

  /**
   * The captured output with its ANSI escape sequences stripped.
   *
   * @return string
   *   The stripped output, convenient for substring assertions.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function display(): string {
    return Ansi::strip($this->output());
  }

  /**
   * The frames the last run() drew, in the order they were drawn.
   *
   * @return list<string>
   *   The frames, each as it reached the terminal.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function frames(): array {
    $frames = [];
    /** @var non-empty-string $clear */
    $clear = TerminalControl::clear();

    // Every frame is written behind a screen clear, which is what tells one
    // from the next; the clear that ends the session writes no frame at all.
    foreach (explode($clear, $this->output()) as $frame) {
      if ($frame !== '') {
        $frames[] = $frame;
      }
    }

    return $frames;
  }

  /**
   * One frame the last run() drew.
   *
   * @param int $index
   *   The frame, counted from the first; a negative index counts back from the
   *   last, so -1 is the frame the session ended on.
   *
   * @return string
   *   The frame, with its ANSI escape sequences stripped.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   * @throws \OutOfBoundsException
   *   When no frame was drawn at that index.
   */
  public function frame(int $index = -1): string {
    $frames = $this->frames();
    $at = $index < 0 ? count($frames) + $index : $index;

    if (!isset($frames[$at])) {
      throw new \OutOfBoundsException(sprintf('The session drew %d frames, so there is none at index %d.', count($frames), $index));
    }

    return Ansi::strip($frames[$at]);
  }

  /**
   * The controller the run drives.
   *
   * @return \DrevOps\Tui\Screen\ScreenController
   *   The controller.
   */
  protected function controller(): ScreenController {
    $options = $this->themeOptions();

    return new ScreenController(
      $this->panel,
      // The same width resolution the facade applies, against the scripted
      // terminal's columns: the theme is what every width is read off, so it
      // is the one place a terminal's size is turned into a frame's.
      $this->theme ?? new DefaultTheme(Tui::frameWidth($options, $this->cols), $options),
      $this->supplied,
      $this->keys,
      $this->collector,
      $this->context,
      $this->layout,
      $this->border,
      $this->clearOnExit,
      $this->footer,
      $this->banner,
      $this->version,
      $this->externalEditor,
    );
  }

  /**
   * The display options the theme is built from.
   *
   * The frame's border is stated on the tester rather than among these, and a
   * theme that believes it is drawing a different one states the room inside a
   * frame that is not the one being drawn. Stating it here keeps the two in
   * step, and an option a consumer sets by hand still wins.
   *
   * @return array<string,mixed>
   *   The options.
   */
  protected function themeOptions(): array {
    return $this->options + ['border' => $this->border->value];
  }

}
