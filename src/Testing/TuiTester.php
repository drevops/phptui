<?php

declare(strict_types=1);

namespace DrevOps\Tui\Testing;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Tui;

/**
 * Drives a form's interactive panel TUI from scripted keystrokes.
 *
 * The form-level companion to {@see \DrevOps\Tui\Testing\WidgetRunner}: it
 * feeds keystrokes through a scripted terminal's read() and runs the real
 * panel loop, so a consumer can assert on the collected answers and on what
 * was rendered - without a real TTY. Keystrokes are supplied as raw byte
 * strings (e.g. the output of a keystroke helper) and/or Key objects, which
 * are encoded to their canonical bytes.
 *
 * @code
 * $answers = (new TuiTester($form))->run('Ada', Key::named(KeyName::Enter));
 * $this->assertSame('Ada', $answers->value('name'));
 * @endcode
 *
 * @package DrevOps\Tui\Testing
 */
final class TuiTester {

  /**
   * The message thrown when a result is read before run() was called.
   */
  protected const string NOT_RUN = 'Call run() before reading the results.';

  /**
   * The facade wrapping the form under test.
   */
  protected Tui $tui;

  /**
   * The theme display options (color, unicode, mode) merged over the defaults.
   *
   * @var array<string,mixed>
   */
  protected array $options = ['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark];

  /**
   * The theme name or class (empty selects the default theme).
   */
  protected string $theme = '';

  /**
   * The reported terminal height.
   */
  protected int $rows = 24;

  /**
   * The reported terminal width.
   */
  protected int $cols = 80;

  /**
   * The version stamped into the run context.
   */
  protected string $version = '';

  /**
   * The target directory (empty for the current working directory).
   */
  protected string $directory = '';

  /**
   * Whether discovery pre-fills the panels from an existing project.
   */
  protected bool $update = FALSE;

  /**
   * The answers collected by the last run(), or NULL before the first run.
   */
  protected ?Answers $answers = NULL;

  /**
   * The output captured by the last run().
   */
  protected string $output = '';

  /**
   * Whether the last run() ended via the cancel button.
   */
  protected bool $cancelled = FALSE;

  /**
   * Whether the last run() ended via the interrupt key (Ctrl-C).
   */
  protected bool $interrupted = FALSE;

  /**
   * Construct a tester for a form.
   *
   * @param \DrevOps\Tui\Model\FormDefinition|\DrevOps\Tui\Builder\Form $form
   *   The form under test: a Form builder or its built definition.
   * @param string[] $handler_namespaces
   *   Namespaces searched for per-field consumer classes.
   * @param string $env_prefix
   *   The env-variable prefix for per-question overrides.
   */
  public function __construct(FormDefinition|Form $form, array $handler_namespaces = [], string $env_prefix = '') {
    $this->tui = new Tui($form, $handler_namespaces, $env_prefix);
  }

  /**
   * Set the theme name or class the form is rendered with.
   *
   * @param string $theme
   *   The theme name or class.
   *
   * @return $this
   *   The tester.
   */
  public function theme(string $theme): self {
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
   *   When the width is below one column - the value feeds fullscreen layout
   *   geometry, so a bogus width fails here rather than mid-render.
   */
  public function cols(int $cols): self {
    if ($cols < 1) {
      throw new \InvalidArgumentException('The terminal width must be at least 1 column.');
    }

    $this->cols = $cols;

    return $this;
  }

  /**
   * Set the version stamped into the run context.
   *
   * @param string $version
   *   The version.
   *
   * @return $this
   *   The tester.
   */
  public function version(string $version): self {
    $this->version = $version;

    return $this;
  }

  /**
   * Set the target directory.
   *
   * @param string $directory
   *   The target directory.
   *
   * @return $this
   *   The tester.
   */
  public function directory(string $directory): self {
    $this->directory = $directory;

    return $this;
  }

  /**
   * Enable update mode so discovery pre-fills the panels from the directory.
   *
   * @param bool $update
   *   Whether discovery runs against the target directory.
   *
   * @return $this
   *   The tester.
   */
  public function update(bool $update = TRUE): self {
    $this->update = $update;

    return $this;
  }

  /**
   * Run the form, feeding it the given scripted keystrokes.
   *
   * @param string|\DrevOps\Tui\Input\Key ...$items
   *   The scripted input: each item is either raw keystroke bytes (a string,
   *   e.g. "\n" or "Ada") or a Key (encoded to its canonical bytes).
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The collected answers.
   */
  public function run(string|Key ...$items): Answers {
    $keystrokes = [];
    foreach ($items as $item) {
      $keystrokes[] = $item instanceof Key ? KeyEncoder::encode($item) : $item;
    }

    $terminal = new BufferedTerminal($keystrokes, $this->rows, $this->cols);

    // The same width resolution interact() applies, against the scripted
    // terminal's columns.
    $width = Tui::frameWidth($this->options, $this->cols);

    $controller = $this->tui->controller($this->options, $this->theme, '', $this->version, $this->directory, $width, $this->update);

    $this->answers = $controller->run($terminal);
    $this->output = $terminal->output();
    $this->cancelled = $controller->isCancelled();
    $this->interrupted = $controller->isInterrupted();

    return $this->answers;
  }

  /**
   * The answers collected by the last run().
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The answers.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function answers(): Answers {
    return $this->answers ?? throw new \LogicException(self::NOT_RUN);
  }

  /**
   * The raw output captured by the last run().
   *
   * @return string
   *   The captured output, including ANSI escape sequences.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function output(): string {
    // Reuse the run() guard.
    $this->answers();

    return $this->output;
  }

  /**
   * The captured output with ANSI escape sequences stripped.
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
   * Whether the last run() ended via the cancel button.
   *
   * @return bool
   *   TRUE when the user activated the cancel button.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function isCancelled(): bool {
    // Reuse the run() guard.
    $this->answers();

    return $this->cancelled;
  }

  /**
   * Whether the last run() ended via the interrupt key (Ctrl-C).
   *
   * @return bool
   *   TRUE when the user aborted with the interrupt key.
   *
   * @throws \LogicException
   *   When run() has not been called yet.
   */
  public function isInterrupted(): bool {
    // Reuse the run() guard.
    $this->answers();

    return $this->interrupted;
  }

}
