<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Builder\FieldBuilder;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Engine\Engine;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Render\PanelController;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Tui;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the progress widget: a display row that runs work when activated.
 */
#[CoversClass(FieldBuilder::class)]
#[CoversClass(PanelBuilder::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Engine::class)]
#[CoversClass(PanelController::class)]
#[CoversClass(DefaultTheme::class)]
#[CoversClass(ProgressReporter::class)]
#[CoversClass(AgentHelp::class)]
#[Group('tui')]
final class ProgressWidgetTest extends TestCase {

  public function testBuilderStoresTheStepsAndWork(): void {
    $field = $this->form(static fn(ProgressReporter $reporter): mixed => $reporter->advance(), 3)->build()->fields()[0];

    $this->assertSame(FieldType::Progress, $field->type);
    $this->assertSame(3, $field->progressSteps);
    $this->assertInstanceOf(\Closure::class, $field->progressWork);
    $this->assertNull($field->progressCurrent);
  }

  public function testActivatingADeterminateRowRunsTheWorkAndCollectsNoAnswer(): void {
    $steps = 0;
    $work = function (ProgressReporter $reporter) use (&$steps): void {
      for ($index = 0; $index < 3; $index++) {
        $steps++;
        $reporter->advance();
      }
    };

    // Drill into the panel, then activate the progress row.
    $answers = (new TuiTester($this->form($work, 3)))->rows(12)->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertSame(3, $steps);
    $this->assertFalse($answers->has('apply'));
  }

  public function testActivatingAnIndeterminateRowTicksLikeASpinner(): void {
    $ticks = 0;
    $work = function (ProgressReporter $reporter) use (&$ticks): void {
      for ($index = 0; $index < 4; $index++) {
        $ticks++;
        $reporter->advance();
      }
    };

    // No steps declared, so the indicator is an indeterminate spinner.
    $answers = (new TuiTester($this->form($work)))->rows(12)->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertSame(4, $ticks);
    $this->assertFalse($answers->has('apply'));
  }

  public function testActivatingARowWithoutWorkIsANoOp(): void {
    $form = Form::create('Apply')->panel('prep', 'Prep', function (PanelBuilder $p): void {
      $p->progress('apply', 'Apply')->steps(3);
    });

    $answers = (new TuiTester($form))->rows(12)->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertFalse($answers->has('apply'));
  }

  public function testHeadlessCollectionOmitsAProgressRow(): void {
    $answers = (new Tui($this->form(static fn(ProgressReporter $reporter): mixed => $reporter->advance(), 3)))->collect('{}');

    $this->assertFalse($answers->has('apply'));
  }

  public function testTheAnswerSchemaOmitsAProgressRow(): void {
    $form = Form::create('Apply')->panel('prep', 'Prep', function (PanelBuilder $p): void {
      $p->text('name', 'Name');
      $p->progress('apply', 'Apply')->steps(1)->run(static fn(ProgressReporter $reporter): mixed => $reporter->advance());
    })->build();

    $schema = (new AgentHelp($form))->generate();

    // A real question keeps its schema property; the progress row carries none.
    $this->assertStringContainsString('name', $schema);
    $this->assertStringNotContainsString('apply', $schema);
  }

  /**
   * A single-panel form whose only row is a progress row.
   *
   * @param \Closure $work
   *   The work the row runs when activated.
   * @param int|null $steps
   *   The step count for a determinate bar, or NULL for a spinner.
   *
   * @return \DrevOps\Tui\Builder\Form
   *   The form.
   */
  protected function form(\Closure $work, ?int $steps = NULL): Form {
    return Form::create('Apply')->panel('prep', 'Prep', function (PanelBuilder $p) use ($work, $steps): void {
      $builder = $p->progress('apply', 'Apply')->run($work);

      if ($steps !== NULL) {
        $builder->steps($steps);
      }
    });
  }

}
