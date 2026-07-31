<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Field\FieldFactory;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Testing\ScreenTester;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that chrome and questions render in the active language end to end.
 */
#[CoversClass(DefaultTheme::class)]
#[CoversClass(FieldFactory::class)]
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

}
