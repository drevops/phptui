<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Translation;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Answers\SummaryFormatter;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Schema\SchemaValidator;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Translation\Translator;
use DrevOps\Tui\Field\FieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that chrome and questions render in the active language end to end.
 */
#[CoversClass(PanelController::class)]
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

  protected function form(): FormDefinition {
    return Form::create('Demo')
      ->panel('general', 'General', function (PanelBuilder $panel): void {
        $panel->text('name', 'Site name')->description('The name.');
        $panel->select('plan', 'Plan')->options(['basic' => 'Basic tier']);
        $panel->rating('grade', 'Grade')->default(5)->captions([5 => 'Excellent']);
        $panel->confirm('agree', 'Agree');
      })
      ->build();
  }

  public function testInteractiveChromeAndQuestionsTranslated(): void {
    $controller = new PanelController($this->form(), new DefaultTheme(60, ['color' => FALSE]));

    $root = Ansi::strip($controller->frame(16));
    // The breadcrumb (form title) and the drill-in panel row (panel title).
    $this->assertStringContainsString('Demostracion', $root);
    $this->assertStringContainsString('General ES', $root);
    // Chrome: the submit/cancel buttons and a footer hint label.
    $this->assertStringContainsString('[ Enviar ]', $root);
    $this->assertStringContainsString('[ Cancelar ]', $root);
    $this->assertStringContainsString('mover', $root);

    // Drilling into the panel shows the field label and description translated.
    $controller->handle(Key::named(KeyName::Enter));
    $panel = Ansi::strip($controller->frame(16));
    $this->assertStringContainsString('Nombre del sitio', $panel);
    $this->assertStringContainsString('El nombre.', $panel);
  }

  public function testOptionLabelsTranslated(): void {
    $field = $this->form()->field('plan');
    $this->assertInstanceOf(Field::class, $field);

    $field = (new FieldFactory())->create($field, 'basic');

    $this->assertStringContainsString('Nivel basico', Ansi::strip($field->view(new DefaultTheme(60, ['color' => FALSE]))));
  }

  public function testRatingCaptionsTranslated(): void {
    $field = $this->form()->field('grade');
    $this->assertInstanceOf(Field::class, $field);
    $theme = new DefaultTheme(60, ['color' => FALSE]);

    // The caption localizes in the editor and in the collapsed panel row alike.
    $field = (new FieldFactory())->create($field, 5);
    $this->assertStringContainsString('Excelente', Ansi::strip($field->view($theme)));

    $controller = new PanelController($this->form(), $theme, ['grade' => 5]);
    $controller->handle(Key::named(KeyName::Enter));
    $this->assertStringContainsString('Excelente', Ansi::strip($controller->frame(16)));
  }

  public function testSummaryTranslated(): void {
    $answers = Answers::forForm($this->form(), ['agree' => TRUE], ['agree' => Provenance::Edited]);

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
