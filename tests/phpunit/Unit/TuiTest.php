<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\CancelException;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Primitive\Progress;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Binding;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMap;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Input\Scope;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Testing\KeyEncoder;
use DrevOps\Tui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\Mode;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Tui;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Tui facade.
 */
#[CoversClass(Tui::class)]
#[CoversClass(Translator::class)]
#[Group('tui')]
final class TuiTest extends TestCase {

  use IsolatesEnvTrait;
  use ResetsTranslatorTrait {
    tearDown as translatorTearDown;
  }

  protected function tearDown(): void {
    $this->restoreEnv();
    $this->translatorTearDown();
  }

  public function testActivatesTranslatorOnRun(): void {
    $this->assertNotInstanceOf(Translator::class, Translator::shared());

    $translator = new Translator('es', [dirname(__DIR__) . '/Fixtures/translations']);

    // An operation activates this facade's own language.
    (new Tui($this->demoForm()))->translator($translator)->collect();

    $this->assertSame($translator, Translator::shared());
  }

  public function testEachOperationRestoresItsOwnTranslator(): void {
    $spanish = (new Tui($this->demoForm()))->translator(new Translator('es', [dirname(__DIR__) . '/Fixtures/translations']));
    $plain = new Tui($this->demoForm());

    // A translator-less facade's operation clears the shared language.
    $plain->collect();
    $this->assertNotInstanceOf(Translator::class, Translator::shared());

    // The translated facade restores its own language on its next operation,
    // even though another facade replaced the shared one meanwhile.
    $spanish->collect();
    $this->assertInstanceOf(Translator::class, Translator::shared());
  }

  public function testKeysResolvesPresetAndOverrides(): void {
    $tui = (new Tui($this->demoForm()))->keys('vim', [new Binding(Scope::navigation(), Action::Quit, 'x')]);

    $keymap = (new \ReflectionProperty($tui, 'keymap'))->getValue($tui);
    $this->assertInstanceOf(KeyMap::class, $keymap);
    $nav = $keymap->navigation();
    // The vim preset supplies "j" for MoveDown; the override binds "x" to Quit.
    $this->assertTrue($nav->matches(Key::char('j'), Action::MoveDown));
    $this->assertTrue($nav->matches(Key::char('x'), Action::Quit));
  }

  public function testKeysThrowsOnInvalidBinding(): void {
    $this->expectException(\InvalidArgumentException::class);

    (new Tui($this->demoForm()))->keys('default', [new Binding(Scope::navigation(), Action::Quit, KeyName::Enter)]);
  }

  public function testFooterAndClearOnExitFlowToController(): void {
    $tui = (new Tui($this->demoForm()))->footer(FALSE)->clearOnExit(FALSE);

    $controller = $tui->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertFalse((new \ReflectionProperty($controller, 'footer'))->getValue($controller));
    $this->assertFalse((new \ReflectionProperty($controller, 'clearOnExit'))->getValue($controller));
  }

  public function testCollect(): void {
    $answers = $this->tui()->collect('{"name":"Acme"}', 'dir', FALSE, '1.0');

    $this->assertSame('Acme', $answers->value('name'));
    // "machine" is derived from "name".
    $this->assertSame('acme', $answers->value('machine'));
  }

  public function testRun(): void {
    // Supplied prompts force the headless path regardless of the TTY.
    $answers = $this->tui()->run('{"name":"Acme"}', '1.0');
    $this->assertSame('Acme', $answers->value('name'));

    // An explicit FALSE forces headless collection from the defaults.
    $answers = $this->tui()->run('', '', '', FALSE);
    $this->assertSame('', $answers->value('name'));
  }

  public function testSchema(): void {
    $this->assertArrayHasKey('prompts', $this->tui()->schema());
  }

  public function testAgentHelp(): void {
    $this->assertStringContainsString('name', $this->tui()->agentHelp());
  }

  public function testEnvPrefix(): void {
    $form = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      })
      ->build();

    // No prefix anywhere falls back to the package default.
    $this->assertStringContainsString('TUI_NAME', (new Tui($form))->agentHelp());
    // A constructor prefix wins.
    $this->assertStringContainsString('ARG_NAME', (new Tui($form, env_prefix: 'ARG_'))->agentHelp());

    $form = Form::create('Demo')
      ->envPrefix('FORM_')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      })
      ->build();

    // The form-declared prefix is used unless the constructor overrides it.
    $this->assertStringContainsString('FORM_NAME', (new Tui($form))->agentHelp());
    $this->assertStringContainsString('ARG_NAME', (new Tui($form, env_prefix: 'ARG_'))->agentHelp());
  }

  public function testValidate(): void {
    $this->assertSame([], $this->tui()->validate(['name' => 'Acme']));
    $this->assertNotSame([], $this->tui()->validate(['bogus' => 'x']));
  }

  public function testAccessors(): void {
    $tui = $this->tui();

    $this->assertSame('Demo', $tui->form()->title);
    $this->assertInstanceOf(Engine::class, $tui->engine());
    $this->assertInstanceOf(HandlerRegistry::class, $tui->registry());
  }

  public function testController(): void {
    $controller = $this->tui()->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertInstanceOf(PanelController::class, $controller);
    // The engine's resolved answers seed the controller.
    $this->assertSame('', $controller->answers()->value('name'));
  }

  public function testInteractDrivesScriptedTerminal(): void {
    // The Demo hub lists the panel, then Submit and Cancel: Down reaches
    // Submit, Enter activates it.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Down)), KeyEncoder::encode(Key::named(KeyName::Enter))]);

    $answers = $this->tui()->interact(terminal: $terminal);

    $this->assertInstanceOf(Answers::class, $answers);
    $this->assertStringContainsString('Demo', $terminal->output());
  }

  public function testInteractThrowsOnInterrupt(): void {
    // Ctrl-C aborts mid-form: the facade raises rather than returning the
    // partial answers, so a caller never mistakes an abort for a submit.
    $this->expectException(InterruptException::class);

    $this->tui()->interact(terminal: new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Interrupt))]));
  }

  public function testInteractThrowsOnCancel(): void {
    // The cancel button is the same abort expressed as a click: the facade
    // raises (a subclass of InterruptException, so one catch covers both)
    // rather than returning the answers exactly like a submitted form.
    $this->expectException(CancelException::class);

    // Down twice reaches Cancel past the panel and Submit; Enter activates it.
    $down = KeyEncoder::encode(Key::named(KeyName::Down));
    $this->tui()->interact(terminal: new BufferedTerminal([$down, $down, KeyEncoder::encode(Key::named(KeyName::Enter))]));
  }

  public function testFullscreenSugarMergesIntoThemeOptions(): void {
    $terminal = new BufferedTerminal();

    // The setter supplies the option.
    $tui = $this->tui()->fullscreen();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertTrue($options['fullscreen']);

    // An explicit theme option wins over the sugar.
    $tui = $this->tui()->theme('', ['fullscreen' => FALSE])->fullscreen();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertFalse($options['fullscreen']);

    // Without either, the option stays unset.
    $tui = $this->tui();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertArrayNotHasKey('fullscreen', $options);
  }

  public function testInteractFullscreenFillsTheScriptedTerminal(): void {
    // No input: the loop renders one frame and stops on exhaustion. The frame
    // stretches to the scripted terminal's exact rows and lays out to its
    // columns rather than the default width - a border pads every line to the
    // full width, so both dimensions are assertable.
    $terminal = new BufferedTerminal([], 16, 50);

    $this->tui()->fullscreen()->color(FALSE)->theme('', ['border' => Border::Line])->interact(terminal: $terminal);

    $lines = explode("\n", Ansi::strip($terminal->output()));
    $this->assertCount(16, $lines);

    foreach ($lines as $line) {
      $this->assertSame(50, mb_strlen($line, 'UTF-8'));
    }
  }

  #[DataProvider('dataProviderResolveTheme')]
  public function testResolveTheme(string $facade_theme, string $theme, string $expected): void {
    $tui = $this->themedTui($facade_theme);
    $resolved = (new \ReflectionMethod($tui, 'resolveTheme'))->invoke($tui, $theme);

    $this->assertSame($expected, $resolved);
  }

  public static function dataProviderResolveTheme(): \Iterator {
    // The argument wins over the facade's theme.
    yield 'argument wins over facade' => ['ocean', 'reef', 'reef'];
    // An empty argument falls back to the facade's theme.
    yield 'facade theme used' => ['ocean', '', 'ocean'];
    // Empty or the "auto" sentinel selects the default theme; the dark/light
    // mode is a separate option now, not a theme choice.
    yield 'empty is default' => ['', '', 'default'];
    yield 'auto argument is default' => ['', 'auto', 'default'];
    yield 'auto facade is default' => ['auto', '', 'default'];
  }

  #[DataProvider('dataProviderResolveThemeOptionsDetectsMode')]
  public function testResolveThemeOptionsDetectsMode(bool $color, ?string $osc, Mode $expected_mode): void {
    $this->putEnv('COLORFGBG', NULL);

    $tui = $this->colouredTui($color);
    $options = (array) (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $this->terminalReturning($osc));

    $this->assertSame($color, $options['color']);
    $this->assertTrue($options['unicode']);
    $this->assertSame($expected_mode, $options['mode']);
  }

  public static function dataProviderResolveThemeOptionsDetectsMode(): \Iterator {
    // With colour on, the mode follows the terminal background.
    yield 'colour on detects light' => [TRUE, "\033]11;rgb:ffff/ffff/ffff\007", Mode::Light];
    yield 'colour on detects dark' => [TRUE, "\033]11;rgb:0000/0000/0000\007", Mode::Dark];
    yield 'colour on no reply defaults dark' => [TRUE, NULL, Mode::Dark];
    // With colour off, the background query is skipped and mode is dark.
    yield 'colour off skips detection' => [FALSE, "\033]11;rgb:ffff/ffff/ffff\007", Mode::Dark];
  }

  public function testResolveThemeOptionsRespectsConsumerOptions(): void {
    $tui = (new Tui($this->demoForm()))->theme('', ['mode' => 'light', 'color' => FALSE, 'unicode' => FALSE]);

    // A consumer's explicit options win over detection.
    $options = (array) (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $this->terminalReturning("\033]11;rgb:0000/0000/0000\007"));

    $this->assertSame('light', $options['mode']);
    $this->assertFalse($options['color']);
    $this->assertFalse($options['unicode']);
  }

  public function testProgressAsSpinnerRunsTheWorkAndReturnsItsResult(): void {
    $terminal = new BufferedTerminal();

    // A NULL total is an indeterminate spinner. Off a TTY it stays plain, but
    // the callback still runs - advancing the forwarded primitive - and its
    // result is passed straight back.
    $result = (new Tui($this->demoForm()))->progress(NULL, 'Scanning', static function (Progress $progress): string {
      $progress->advance();

      return 'scanned';
    }, $terminal);

    $this->assertSame('scanned', $result);
    $this->assertSame("Scanning\n", $terminal->output());
  }

  public function testProgressAsBarRunsTheWorkAndReturnsItsResult(): void {
    $terminal = new BufferedTerminal();

    // A known total is a determinate bar.
    $result = (new Tui($this->demoForm()))->progress(3, 'Packing', static function (Progress $progress): string {
      $progress->advance('apples');

      return 'packed';
    }, $terminal);

    $this->assertSame('packed', $result);
    $this->assertSame("Packing\n", $terminal->output());
  }

  /**
   * A TUI over a small in-memory form.
   */
  protected function tui(): Tui {
    $form = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name')->required();
        $panel->text('machine')->derive(new Derive('{{name}}', 'machine'));
      });

    return new Tui($form, env_prefix: 'TEST_');
  }

  /**
   * A minimal Demo form builder.
   */
  protected function demoForm(): Form {
    return Form::create('Demo')->panel('p', 'p', function (PanelBuilder $panel): void {
      $panel->text('name');
    });
  }

  /**
   * A TUI configured with the given theme.
   */
  protected function themedTui(string $theme): Tui {
    return (new Tui($this->demoForm()))->theme($theme);
  }

  /**
   * A TUI forcing colour on or off (and Unicode on).
   */
  protected function colouredTui(bool $color): Tui {
    return (new Tui($this->demoForm()))->color($color)->unicode(TRUE);
  }

  /**
   * A terminal whose background query yields a fixed reply.
   */
  protected function terminalReturning(?string $response): Terminal {
    return new class($response) extends Terminal {

      public function __construct(protected ?string $response) {
        parent::__construct();
      }

      #[\Override]
      public function queryBackground(): ?string {
        return $this->response;
      }

    };
  }

}
