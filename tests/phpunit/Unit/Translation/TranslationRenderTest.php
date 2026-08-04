<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Field\FieldFactory;
use DrevOps\Tui\Field\FilePicker;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Testing\ScreenTester;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that chrome and questions render in the active language end to end.
 *
 * Two languages, for two different questions. A fixture catalog answers
 * whether a string reaches the translator at all, and is deliberately partial
 * so an untranslated one still shows. The bundled Ukrainian catalog answers
 * whether the package alone, with nothing configured, puts a whole session in
 * front of a reader in their language.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(FieldFactory::class)]
#[CoversClass(Legend::class)]
#[CoversClass(Provenance::class)]
#[CoversClass(SummaryFormatter::class)]
#[CoversClass(SchemaValidator::class)]
#[CoversClass(AgentHelp::class)]
#[Group('translation')]
final class TranslationRenderTest extends TestCase {

  use ResetsTranslatorTrait;

  protected function setUp(): void {
    parent::setUp();
    Translator::setShared(new Translator('es', [dirname(__DIR__, 2) . '/Fixtures/translations-render']));
  }

  /**
   * The declared tree every scenario is driven against.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel every declared panel hangs from.
   */
  protected function form(): Panel {
    return Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $panel): void {
        $panel->text('name', 'Site name')->description('The name.');
        $panel->select('plan', 'Plan')->options(['basic' => 'Basic tier']);
        $panel->rating('grade', 'Grade')->default(5)->captions([5 => 'Excellent']);
        $panel->confirm('agree', 'Agree');
      })
      ->root();
  }

  public function testInteractiveChromeAndQuestionsTranslated(): void {
    $tester = (new ScreenTester($this->form()))->rows(16)->cols(60);

    // Going into the panel is what puts its rows in front of the reader, so
    // one run covers the form's chrome and the questions it asks.
    $tester->run(Key::named(KeyName::Enter));

    $root = $tester->frame(0);
    // The breadcrumb (form title) and the drill-in panel row (panel title).
    $this->assertStringContainsString('Demostracion', $root);
    $this->assertStringContainsString('General ES', $root);
    // Chrome: the submit/cancel buttons and a footer hint label.
    $this->assertStringContainsString('[ Enviar ]', $root);
    $this->assertStringContainsString('[ Cancelar ]', $root);
    $this->assertStringContainsString('mover', $root);

    $panel = $tester->frame();
    $this->assertStringContainsString('Nombre del sitio', $panel);
    $this->assertStringContainsString('El nombre.', $panel);
  }

  public function testOptionLabelsTranslated(): void {
    $tester = (new ScreenTester($this->form()))->rows(16)->cols(60);

    // Into the panel, down to the choice, and open it.
    $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertStringContainsString('Nivel basico', $tester->frame());
  }

  public function testRatingCaptionsTranslated(): void {
    $tester = (new ScreenTester($this->form()))->rows(16)->cols(60);

    // The caption localizes in the editor and in the settled row alike, so the
    // frame the scale is opened on and the one it closes back to both say it.
    $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertStringContainsString('Excelente', $tester->frame());
    $this->assertStringContainsString('Excelente', $tester->frame(-2));
  }

  public function testSummaryTranslated(): void {
    $answers = Answers::forTree($this->form(), ['agree' => TRUE], ['agree' => Provenance::Edited]);

    $summary = (new SummaryFormatter())->format($answers);

    $this->assertStringContainsString('General ES', $summary);
    $this->assertStringContainsString('De acuerdo', $summary);
    $this->assertStringContainsString('si', $summary);
    $this->assertStringContainsString('editado', $summary);
  }

  public function testHeadlessMessagesTranslated(): void {
    $form = Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $panel): void {
        $panel->text('name', 'Site name')->required();
      })
      ->root();

    // A headless validation error and the agent help both localize.
    $this->assertContains('Falta la pregunta obligatoria "name".', (new SchemaValidator($form))->validate([]));
    $this->assertStringContainsString('Nombre del sitio', (new AgentHelp($form, 'TUI_'))->generate());
  }

  public function testUkrainianLegendNamesEveryKeyItAdvertises(): void {
    $this->ukrainian();

    $tester = (new ScreenTester($this->weeklyBox()))->rows(16)->cols(80);
    $tester->run(...$this->walk());

    // Closed over the panel: moving, opening a row, stepping back out, and the
    // way out of the session itself.
    $this->assertSame('↑/↓ рух · ↵ вибрати · ESC назад · Q вийти', $this->legend($tester->frame(1)));

    // Open over a multi-select: the fragments only an open list advertises.
    $this->assertSame('ПРОБІЛ вибрати · ↑/↓ рух · ←/→ нічого/усе · ↵ прийняти · ESC скасувати', $this->legend($tester->frame(2)));

    // Help is offered on the row that has some, so the fragment arrives last.
    $this->assertSame('↑/↓ рух · ↵ вибрати · ESC назад · Q вийти · ? довідка', $this->legend($tester->frame()));
  }

  public function testUkrainianLegendFitsTheDefaultFrameWithNothingDropped(): void {
    $this->ukrainian();

    $narrow = (new ScreenTester($this->weeklyBox()))->rows(16)->cols(80);
    $narrow->run(...$this->walk());

    // The same walk through a frame nothing clips. A legend out of room drops
    // whole hints from the end, so a fragment grown past the width a session
    // is normally drawn at costs a reader the way out of a list rather than a
    // few characters of one.
    $wide = (new ScreenTester($this->weeklyBox()))->rows(16)->cols(200)->options(['fullscreen' => TRUE]);
    $wide->run(...$this->walk());

    foreach (array_keys($wide->frames()) as $at) {
      $composed = $this->legend($wide->frame($at));

      $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, Ansi::width($composed));
      $this->assertSame($composed, $this->legend($narrow->frame($at)));
    }
  }

  public function testUkrainianFilePickerAdvertisesItsWholeListInsideTheDefaultFrame(): void {
    $this->ukrainian();

    $picker = new FilePicker(__DIR__);
    $legend = (new Legend())->advertise(KeyMapManager::create()->forField(FieldType::FilePicker), ...$picker->hints());
    $composed = Ansi::strip($legend->render(new DefaultTheme(DefaultTheme::DEFAULT_WIDTH * 2, ['color' => FALSE, 'unicode' => TRUE])));

    // The longest list anything here advertises: browsing adds a way in, a way
    // out and a way to see what is hidden on top of the four every field has.
    $this->assertSame('↑/↓ рух · → відкрити · ← вгору · ↵ вибрати · TAB приховані · ESC скасувати', $composed);
    $this->assertLessThanOrEqual(DefaultTheme::DEFAULT_WIDTH, Ansi::width($composed));
  }

  public function testUkrainianRowStatesItsRefusalAndWhereItsValueCameFrom(): void {
    $this->ukrainian();

    $form = Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel): void {
        $panel->text('courier', 'Courier')->required();
        $panel->text('crate', 'Crate')->default('Valley Runs');
      })
      ->root();

    $tester = (new ScreenTester($form))->rows(14)->cols(70)->supplied(['crate' => 'Ridge Runs']);
    $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
    );

    // The refusal names the field and says, in Ukrainian, what it is owed; the
    // badge beside the row it did not refuse says where that value came from.
    $frame = $tester->frame();
    $this->assertStringContainsString("є обов'язковим полем.", $frame);
    $this->assertStringContainsString('змінено', $frame);
  }

  #[DataProvider('dataProviderUkrainianCountPhraseTakesTheFormTheCountCallsFor')]
  public function testUkrainianCountPhraseTakesTheFormTheCountCallsFor(int $minimum, string $expected): void {
    $this->ukrainian();

    $form = Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel) use ($minimum): void {
        $panel->select('basket', 'Basket')->multiple()->minSelections($minimum)
          ->options(['apple' => 'Apple', 'beet' => 'Beet', 'carrot' => 'Carrot', 'date' => 'Date', 'endive' => 'Endive', 'fennel' => 'Fennel']);
      })
      ->root();

    $tester = (new ScreenTester($form))->rows(16)->cols(70);
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertStringContainsString($expected, $tester->frame());
  }

  public static function dataProviderUkrainianCountPhraseTakesTheFormTheCountCallsFor(): \Iterator {
    // Ukrainian's three forms, each reached through the count a bound states.
    yield 'one' => [1, 'Виберіть щонайменше 1 елемент.'];
    yield 'few' => [3, 'Виберіть щонайменше 3 елементи.'];
    yield 'many' => [5, 'Виберіть щонайменше 5 елементів.'];
  }

  public function testUkrainianPanelRowCountsThePicksItHasNoRoomToList(): void {
    $this->ukrainian();

    $form = Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel): void {
        $panel->select('basket', 'Basket')->multiple()->default(['apple', 'beet', 'carrot', 'date'])
          ->options(['apple' => 'Apple', 'beet' => 'Beet', 'carrot' => 'Carrot', 'date' => 'Date']);
      })
      ->root();

    $tester = (new ScreenTester($form))->rows(12)->cols(70);
    $tester->run();

    // Past a handful the row says how many were picked rather than listing
    // them, which is the one count phrase a panel of its own renders.
    $this->assertStringContainsString('4 елементи вибрано', $tester->frame());
  }

  public function testUkrainianCalendarNamesTheMonthItOpensOn(): void {
    $this->ukrainian();

    $form = Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel): void {
        $panel->calendar('due', 'Due date')->default('2026-03-15');
      })
      ->root();

    $tester = (new ScreenTester($form))->rows(16)->cols(70);
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    // The heading and the weekday row are formatted from the date, not written
    // out in the source, and both still arrive in Ukrainian.
    $frame = $tester->frame();
    $this->assertStringContainsString('Березень 2026', $frame);
    $this->assertStringContainsString('Пн', $frame);
    $this->assertStringContainsString('←/→ на день', $frame);
    $this->assertStringContainsString('ESC скасувати', $frame);
  }

  public function testUkrainianSummaryAndHeadlessMessagesLocalize(): void {
    $this->ukrainian();

    $form = Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel): void {
        $panel->text('courier', 'Courier')->required();
      })
      ->root();

    $answers = Answers::forTree($form, ['courier' => 'Valley Runs'], ['courier' => Provenance::Edited]);

    $this->assertStringContainsString('(змінено)', (new SummaryFormatter())->format($answers));
    $this->assertContains('Пропущено потрібне питання "courier".', (new SchemaValidator($form))->validate([]));
  }

  /**
   * Put the session into Ukrainian, on the package's own catalogs alone.
   *
   * No source is passed: what the assertions read is what a consumer gets from
   * the package with nothing but a language named.
   */
  protected function ukrainian(): void {
    Translator::setShared(new Translator('uk'));
  }

  /**
   * The tree the legend scenarios are driven against.
   *
   * @return \DrevOps\Tui\Block\Panel
   *   The panel the declared panel hangs from.
   */
  protected function weeklyBox(): Panel {
    return Form::create('Produce order')
      ->panel('order', 'Weekly box', static function (PanelBuilder $panel): void {
        $panel->select('basket', 'Basket')->multiple()->options(['apple' => 'Apple', 'beet' => 'Beet']);
        $panel->text('courier', 'Courier')->help('Weighed at the packing bench.');
      })
      ->root();
  }

  /**
   * The keystrokes that walk a session past each legend it draws.
   *
   * @return list<\DrevOps\Tui\Input\Key>
   *   The keystrokes: into the panel, into the list, back out, onto the row
   *   that has help.
   */
  protected function walk(): array {
    return [
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Escape),
      Key::named(KeyName::Down),
    ];
  }

  /**
   * The keys line a frame advertises.
   *
   * @param string $frame
   *   The frame, with its ANSI escape sequences already stripped.
   *
   * @return string
   *   The line, empty when the frame advertises nothing.
   */
  protected function legend(string $frame): string {
    $line = '';

    // The last one it draws: the keys sit at the foot of the frame, below
    // anything else a separator could appear in.
    foreach (explode("\n", $frame) as $row) {
      if (str_contains($row, '·')) {
        $line = trim($row);
      }
    }

    return $line;
  }

}
