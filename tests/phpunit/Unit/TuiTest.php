<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit;

use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Answers\Provenance;
use DrevOps\PhpTui\Block\Actions;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\CancelException;
use DrevOps\PhpTui\Derive\Derive;
use DrevOps\PhpTui\Discovery\Dotenv;
use DrevOps\PhpTui\Discovery\JsonValue;
use DrevOps\PhpTui\Handler\Context;
use DrevOps\PhpTui\Handler\HandlerRegistry;
use DrevOps\PhpTui\Input\Action;
use DrevOps\PhpTui\Input\Binding;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyMap;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Input\Scope;
use DrevOps\PhpTui\InterruptException;
use DrevOps\PhpTui\Primitive\Progress;
use DrevOps\PhpTui\Screen\Axis;
use DrevOps\PhpTui\Screen\ScreenController;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Terminal\Terminal;
use DrevOps\PhpTui\Terminal\TerminalControl;
use DrevOps\PhpTui\Testing\BufferedTerminal;
use DrevOps\PhpTui\Testing\KeyEncoder;
use DrevOps\PhpTui\Testing\TuiTester;
use DrevOps\PhpTui\Tests\Fixtures\Theme\FloorTheme;
use DrevOps\PhpTui\Tests\Traits\IsolatesEnvTrait;
use DrevOps\PhpTui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Mode;
use DrevOps\PhpTui\Theme\Override\BreadcrumbOverrides;
use DrevOps\PhpTui\Theme\Override\FieldOverrides;
use DrevOps\PhpTui\Theme\ThemeBuilder;
use DrevOps\PhpTui\Translation\Translator;
use DrevOps\PhpTui\Tui;
use org\bovigo\vfs\vfsStream;
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

    $keymap = (new \ReflectionProperty($tui, 'keyMap'))->getValue($tui);
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

  public function testRunThreadsUpdateToCollect(): void {
    vfsStream::setup('project', NULL, [
      'box.json' => '{"name": "Weekly Box"}',
      '.env' => 'SEASON=winter',
    ]);
    $dir = vfsStream::url('project');

    // Forced headless: run() forwards its update flag to collect(), so
    // discovery pre-fills the answers with detected provenance.
    $answers = $this->discoveryTui()->run('', '', $dir, FALSE, TRUE);
    $this->assertSame('Weekly Box', $answers->value('name'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('name'));

    // Update off (the default): the plain declared defaults collect instead.
    $answers = $this->discoveryTui()->run('', '', $dir, FALSE);
    $this->assertSame('', $answers->value('name'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('name'));
  }

  public function testSchema(): void {
    $this->assertArrayHasKey('prompts', $this->tui()->schema());
  }

  public function testAgentHelp(): void {
    $this->assertStringContainsString('name', $this->tui()->agentHelp());
  }

  public function testSchemaResolvesClosureDefaultWithContext(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('Version', 'version')->default(fn (Context $context): string => $context->version);
      });

    $prompts = (new Tui($form))->schema(new Context(version: '4.5.6'))['prompts'];
    $this->assertIsArray($prompts);
    $first = $prompts[0];
    $this->assertIsArray($first);
    $this->assertSame('4.5.6', $first['default']);
  }

  public function testAgentHelpResolvesClosureDefaultWithContext(): void {
    $form = Form::create('T')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('Version', 'version')->default(fn (Context $context): string => $context->version);
      });

    $help = (new Tui($form))->agentHelp(new Context(version: '4.5.6'));

    $this->assertStringContainsString('"default": "4.5.6"', $help);
  }

  public function testEnvPrefix(): void {
    $form = Form::create('Demo')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });

    // No prefix anywhere falls back to the package default.
    $this->assertStringContainsString('PHPTUI_NAME', (new Tui($form))->agentHelp());
    // A constructor prefix wins.
    $this->assertStringContainsString('ARG_NAME', (new Tui($form, env_prefix: 'ARG_'))->agentHelp());

    $form = Form::create('Demo')
      ->envPrefix('FORM_')
      ->panel('p', 'p', function (PanelBuilder $panel): void {
        $panel->text('name');
      });

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

    $this->assertInstanceOf(Panel::class, $tui->root());
    $this->assertSame('Demo', $tui->root()->title());
    $this->assertInstanceOf(HandlerRegistry::class, $tui->registry());
  }

  public function testController(): void {
    $controller = $this->tui()->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $this->assertInstanceOf(ScreenController::class, $controller);
    // The answers the form opens on seed the session.
    $this->assertSame('', $controller->answers()->value('name'));
  }

  public function testLayoutArrangesTheSessionItNames(): void {
    $form = Form::create('Orchard')->panel('Delivery', 'main', function (PanelBuilder $p): void {
      $p->text('Courier', 'courier')->default('Valley Runs');
    });

    $tester = (new TuiTester($form))->layout('two-column');
    $tester->run("\n");

    // A two-column screen has no header region, so the trail that the default
    // layout pins up top is simply not drawn - which is only true when the
    // named layout actually reached the session.
    $this->assertStringNotContainsString('Orchard', $tester->output());
  }

  public function testLayoutRefusesNameNothingShipsOrRegisters(): void {
    $this->expectException(\InvalidArgumentException::class);

    $this->tui()->layout('orchard-grid');
  }

  public function testPlacedBlocksStandBesideTheFurnitureTheirRegionAlreadyHolds(): void {
    $tester = (new TuiTester($this->demoForm()))
      ->options(['color' => FALSE, 'border' => Border::None])
      ->cols(80)
      ->place('header', new Markup('preview', '(read-only preview)'))
      ->place('footer', new Markup('version', 'v1.2.3'), tail: TRUE)
      ->flow('header', Axis::Columns)
      ->flow('footer', Axis::Columns);

    $tester->run(Key::named(KeyName::Interrupt));
    $lines = explode("\n", $tester->display());

    // The furniture is placed first, so what the consumer adds lands after the
    // trail rather than in front of it - and running across is what puts the
    // two on one row instead of two.
    $this->assertSame('Demo (read-only preview)', rtrim($lines[0]));
    // Packing from the end is what holds the version against the far edge.
    $this->assertStringEndsWith('v1.2.3', rtrim($lines[array_key_last($lines)]));
  }

  public function testRegionNobodyTurnedRunsItsBlocksDownItself(): void {
    $tester = (new TuiTester($this->demoForm()))
      ->options(['color' => FALSE, 'border' => Border::None])
      ->cols(60)
      ->place('header', new Markup('preview', '(read-only preview)'));

    $tester->run(Key::named(KeyName::Interrupt));
    $lines = explode("\n", $tester->display());

    // Down a header of one row the trail has it and the block has nowhere to
    // go, because a region that was not declared to scroll clips what outruns
    // it. Turning the region across is what makes room for both.
    $this->assertSame('Demo', rtrim($lines[0]));
    $this->assertStringNotContainsString('(read-only preview)', $tester->display());
  }

  #[DataProvider('dataProviderRegionNameNothingAnswersToThrowsWhereItIsWritten')]
  public function testRegionNameNothingAnswersToThrowsWhereItIsWritten(\Closure $state): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown region "orchard"');

    $state($this->tui());
  }

  public static function dataProviderRegionNameNothingAnswersToThrowsWhereItIsWritten(): \Iterator {
    yield 'placing' => [static fn(Tui $tui): Tui => $tui->place('orchard', new Markup('preview', 'Preview'))];
    yield 'flowing' => [static fn(Tui $tui): Tui => $tui->flow('orchard', Axis::Columns)];
    // Named before the layout that would have to answer to it: the check is
    // made again from there, so the order the two are written in is free.
    yield 'placing before the layout' => [static fn(Tui $tui): Tui => $tui->place('orchard', new Markup('preview', 'Preview'))->layout('two-column')];
    yield 'flowing before the layout' => [static fn(Tui $tui): Tui => $tui->flow('orchard', Axis::Columns)->layout('two-column')];
  }

  public function testEverySessionDrivesTheOneDeclaredTree(): void {
    $tui = $this->tui();

    // The declaration is the tree, so a second session drives the very blocks
    // the first one did rather than a copy that could disagree with it.
    $first = $tui->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);
    $second = $tui->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    $panel = static fn(ScreenController $controller): mixed => (new \ReflectionProperty($controller, 'panel'))->getValue($controller);

    $this->assertSame($tui->root(), $panel($first));
    $this->assertSame($panel($first), $panel($second));

    // And drives it as it was declared: the way out of the form is the
    // session's own, so neither session wrote one into the tree for the next
    // one to inherit.
    $actions = array_filter($tui->root()->place()->blocks(), static fn(object $block): bool => $block instanceof Actions);
    $this->assertCount(0, $actions);
  }

  #[DataProvider('dataProviderControllerCarriesTheThemeBorderIntoTheFrame')]
  public function testControllerCarriesTheThemeBorderIntoTheFrame(array $options, Border $expected): void {
    $controller = $this->tui()->controller($options + ['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark]);

    // The frame the theme lays its rows out to is the frame drawn around them,
    // so the border reaches the session rather than the theme alone.
    $this->assertSame($expected, (new \ReflectionProperty($controller, 'border'))->getValue($controller));
  }

  public static function dataProviderControllerCarriesTheThemeBorderIntoTheFrame(): \Iterator {
    yield 'declared' => [['border' => Border::Double], Border::Double];
    yield 'declared as its name' => [['border' => 'none'], Border::None];
    // A form is framed unless it asks not to be, and the theme is what says so.
    yield 'undeclared' => [[], Border::Rounded];
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

    // Down reaches the row the buttons share and Right walks along it to
    // Cancel; Enter activates it.
    $down = KeyEncoder::encode(Key::named(KeyName::Down));
    $right = KeyEncoder::encode(Key::named(KeyName::Right));
    $this->tui()->interact(terminal: new BufferedTerminal([$down, $right, KeyEncoder::encode(Key::named(KeyName::Enter))]));
  }

  public function testInteractUpdateModePreFillsDetectedValues(): void {
    vfsStream::setup('project', NULL, [
      'box.json' => '{"name": "Weekly Box"}',
      '.env' => 'SEASON=winter',
    ]);
    $dir = vfsStream::url('project');

    // Update on: interact() carries the flag to the initial state resolution,
    // so the panels open pre-filled with the detected values and their badges.
    $answers = $this->discoveryTui()->interact(directory: $dir, update: TRUE, terminal: new BufferedTerminal([]));
    $this->assertSame('Weekly Box', $answers->value('name'));
    $this->assertSame('winter', $answers->value('season'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('name'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('season'));

    // Update off (the default): the panels open on the plain declared defaults.
    $answers = $this->discoveryTui()->interact(directory: $dir, terminal: new BufferedTerminal([]));
    $this->assertSame('', $answers->value('name'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('name'));
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

  public function testMarkdownFlowsToThemeOptions(): void {
    $terminal = new BufferedTerminal();

    // Off by default: the theme sees markdown disabled.
    $tui = $this->tui();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertFalse($options['markdown']);

    // The setter turns it on.
    $tui = $this->tui()->markdown();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertTrue($options['markdown']);

    // An explicit theme option wins over the setter.
    $tui = $this->tui()->theme('', ['markdown' => FALSE])->markdown();
    $options = (new \ReflectionMethod($tui, 'resolveThemeOptions'))->invoke($tui, $terminal);
    $this->assertIsArray($options);
    $this->assertFalse($options['markdown']);
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

  #[DataProvider('dataProviderThemeClosureStatesWhatElementsDraw')]
  public function testThemeClosureStatesWhatElementsDraw(bool $unicode, string $separator, string $selector): void {
    // One Enter descends into the panel, so the trail gains a segment and the
    // rows the field selector marks are on screen.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Enter))], 20, 60);

    $this->tui()
      ->color(FALSE)
      ->unicode($unicode)
      ->theme(static fn(ThemeBuilder $builder): ThemeBuilder => $builder
        ->breadcrumb(static fn(BreadcrumbOverrides $group): BreadcrumbOverrides => $group->separator('»', '::'))
        ->field(static fn(FieldOverrides $group): FieldOverrides => $group->selector('→', '=>')))
      ->interact(terminal: $terminal);

    $screen = Ansi::strip($terminal->output());

    $this->assertStringContainsString($separator, $screen);
    $this->assertStringContainsString($selector, $screen);
    // Both display modes are stated together, so neither can be set and the
    // other silently left broken.
    $this->assertStringNotContainsString($unicode ? '::' : '»', $screen);
  }

  public static function dataProviderThemeClosureStatesWhatElementsDraw(): \Iterator {
    yield 'unicode' => [TRUE, '»', '→'];
    yield 'ascii' => [FALSE, '::', '=>'];
  }

  public function testThemeClosurePatchesWhicheverThemeIsSelected(): void {
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Enter))], 20, 60);

    // A name picks the theme and a closure patches it, in either order: the
    // patch is not a second theme.
    $this->tui()
      ->theme(static fn(ThemeBuilder $builder): ThemeBuilder => $builder->field(static fn(FieldOverrides $group): FieldOverrides => $group->selector('→', '=>')))
      ->theme('mono', ['color' => TRUE, 'unicode' => TRUE])
      ->interact(terminal: $terminal);

    $output = $terminal->output();

    // The glyph is the consumer's and the hue is the theme's, so an override
    // names the mark without taking the palette with it.
    $this->assertStringContainsString("\033[1;97m→", $output);
  }

  #[DataProvider('dataProviderFloorThemeRunsTheWholeSession')]
  public function testFloorThemeRunsTheWholeSession(bool $color, bool $unicode): void {
    $output = $this->floorRun($color, $unicode)->output();

    $this->assertStringContainsString('Demo', Ansi::strip($output));

    // The floor declares neither colour nor Unicode, so neither switch reaches
    // it: nothing but the screen clears is escaped, and the cursor is the
    // stand-in that reads without a glyph.
    $this->assertSame(Ansi::strip($output), str_replace(TerminalControl::clear(), '', $output));
    $this->assertStringNotContainsString('❯', $output);
  }

  public static function dataProviderFloorThemeRunsTheWholeSession(): \Iterator {
    yield 'plain terminal' => [FALSE, FALSE];
    yield 'capable terminal' => [TRUE, TRUE];
  }

  public function testFloorThemeDrawsTheSameWhateverTheTerminalOffers(): void {
    // A theme declaring nothing is granted nothing, so the two terminals are
    // handed the identical frame.
    $this->assertSame($this->floorRun(FALSE, FALSE)->output(), $this->floorRun(TRUE, TRUE)->output());
  }

  public function testFloorThemeIsDrawnWithNoFrameAroundIt(): void {
    $controller = $this->tui()->controller(['color' => FALSE, 'unicode' => TRUE, 'mode' => Mode::Dark], FloorTheme::class);

    // A theme that says nothing about the room it takes gets no edge drawn
    // around it, rather than the session guessing one on its behalf.
    $this->assertSame(Border::None, (new \ReflectionProperty($controller, 'border'))->getValue($controller));
  }

  public function testFloorThemeKeepsItsOwnAnswersUnderThePatch(): void {
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Enter))], 20, 60);

    (new Tui($this->demoForm()))
      ->color(FALSE)
      ->unicode(TRUE)
      ->theme(static fn(ThemeBuilder $builder): ThemeBuilder => $builder->field(static fn(FieldOverrides $group): FieldOverrides => $group->selector('→', '=>')))
      ->theme(FloorTheme::class)
      ->interact(terminal: $terminal);

    // Taking a patch is a facility a theme declares; one with nowhere to put it
    // keeps every answer of its own rather than refusing to draw.
    $this->assertStringNotContainsString('→', Ansi::strip($terminal->output()));
  }

  public function testPrimitiveNamesTheThemeThatCannotDrawIt(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot draw a primitive');

    (new Tui($this->demoForm()))->theme(FloorTheme::class)->output(new BufferedTerminal());
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

  public function testOutputWritesThemedChromeToTheGivenTerminal(): void {
    $terminal = new BufferedTerminal();

    // Force the glyphs: left to detect, they would follow the machine's locale
    // and the asserted glyph would differ between environments.
    (new Tui($this->demoForm()))->unicode(TRUE)->output($terminal)
      ->box('Pick your fruit.', 'Welcome')
      ->success('Preserves are ready')
      ->definitions(['Jars' => '12']);

    $output = $terminal->output();

    $this->assertStringContainsString('Welcome', $output);
    $this->assertStringContainsString('Pick your fruit.', $output);
    $this->assertStringContainsString('✓ Preserves are ready', $output);
    $this->assertStringContainsString('Jars', $output);
  }

  public function testOutputDropsAutoDetectedColourOffTerminal(): void {
    $terminal = new BufferedTerminal();

    // The in-memory stream is not a TTY, so escape codes would land in the
    // captured text rather than on a terminal.
    (new Tui($this->demoForm()))->output($terminal)->success('Preserves are ready');

    $this->assertStringNotContainsString("\033", $terminal->output());
  }

  public function testOutputKeepsForcedColourOffTerminal(): void {
    $terminal = new BufferedTerminal();

    (new Tui($this->demoForm()))->color(TRUE)->output($terminal)->success('Preserves are ready');

    // Forcing wins over the stream check: the default dark value colour shows.
    $this->assertStringContainsString("\033[32m", $terminal->output());
  }

  public function testOutputHonoursForcedAsciiGlyphs(): void {
    $terminal = new BufferedTerminal();

    (new Tui($this->demoForm()))->unicode(FALSE)->output($terminal)->box('Fruit', 'Welcome');

    $output = $terminal->output();

    $this->assertStringContainsString('+', $output);
    $this->assertStringNotContainsString('╭', $output);
  }

  public function testOutputWrapsToNarrowTerminal(): void {
    // A frame sized past the terminal hard-wraps and corrupts the layout, so
    // the box follows the narrower of the terminal and the default width.
    $terminal = new BufferedTerminal(cols: 30);

    (new Tui($this->demoForm()))->output($terminal)->box('The weekly produce box carries apples, pears, plums, carrots and a bunch of fresh herbs.');

    foreach (explode("\n", trim($terminal->output(), "\n")) as $line) {
      $this->assertLessThanOrEqual(30, Ansi::width($line));
    }
  }

  public function testOutputHonoursTheFacadeMarkdownSwitch(): void {
    $off = new BufferedTerminal();
    $on = new BufferedTerminal();

    (new Tui($this->demoForm()))->output($off)->text('Pick a **ripe** one');
    (new Tui($this->demoForm()))->markdown()->output($on)->text('Pick a **ripe** one');

    // Off, the markup is literal text; on, the subset is consumed and styled.
    $this->assertStringContainsString('**ripe**', $off->output());
    $this->assertStringNotContainsString('**', $on->output());
    $this->assertStringContainsString('ripe', $on->output());
  }

  public function testOutputUsesTheFacadeTheme(): void {
    $terminal = new BufferedTerminal();

    (new Tui($this->demoForm()))->theme('ember')->color(TRUE)->output($terminal)->info('Packing the order');

    // Ember's accent (bold orange) carries into the info line.
    $this->assertStringContainsString("\033[1;38;5;208m", $terminal->output());
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
   * A TUI whose fields discover their defaults from the project directory.
   */
  protected function discoveryTui(): Tui {
    $form = Form::create('Update')
      ->panel('Box', 'box', function (PanelBuilder $panel): void {
        $panel->text('Box name', 'name')->default('')->discover(new JsonValue('box.json', 'name'));
        $panel->text('Season', 'season')->default('summer')->discover(new Dotenv('SEASON'));
      });

    return new Tui($form);
  }

  /**
   * A TUI configured with the given theme.
   */
  protected function themedTui(string $theme): Tui {
    return (new Tui($this->demoForm()))->theme($theme);
  }

  /**
   * Run a whole session on a theme named for a class built on the floor.
   *
   * Selected the way any other theme is and driven to the end, so what comes
   * back is a session a consumer could have run rather than one only a harness
   * can reach.
   *
   * @param bool $color
   *   Whether the terminal is told to paint.
   * @param bool $unicode
   *   Whether the terminal is told it draws glyphs outside ASCII.
   *
   * @return \DrevOps\PhpTui\Testing\BufferedTerminal
   *   The terminal the session drew on.
   */
  protected function floorRun(bool $color, bool $unicode): BufferedTerminal {
    // Down reaches Submit and Enter presses it, so the session opens a panel,
    // draws every piece of furniture around it and ends.
    $terminal = new BufferedTerminal([KeyEncoder::encode(Key::named(KeyName::Down)), KeyEncoder::encode(Key::named(KeyName::Enter))], 20, 60);

    $answers = (new Tui($this->demoForm()))->color($color)->unicode($unicode)->theme(FloorTheme::class)->interact(terminal: $terminal);

    $this->assertInstanceOf(Answers::class, $answers);

    return $terminal;
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
