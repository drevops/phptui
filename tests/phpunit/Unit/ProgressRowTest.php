<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit;

use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Schema\AgentHelp;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Testing\TuiTester;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Tui;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the progress row: a display row that runs work when activated.
 */
#[CoversClass(PanelBuilder::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Collector::class)]
#[CoversClass(ScreenController::class)]
#[CoversClass(DefaultTheme::class)]
#[CoversClass(ProgressReporter::class)]
#[CoversClass(AgentHelp::class)]
#[Group('tui')]
final class ProgressRowTest extends TestCase {

  public function testBuilderStoresTheStepsAndWork(): void {
    $block = $this->form($this->work(), 3)->root()->children()[0]->in('content')->blocks()[0];

    $this->assertInstanceOf(Progress::class, $block);
    $this->assertSame(3, $block->total());
    $this->assertInstanceOf(\Closure::class, $block->workload());
  }

  #[DataProvider('dataProviderNonPositiveStepsAreRejected')]
  public function testNonPositiveStepsAreRejected(int $total): void {
    $this->expectException(FormException::class);

    (new Progress('apply', 'Apply'))->steps($total);
  }

  /**
   * Data provider for testNonPositiveStepsAreRejected().
   *
   * @return \Iterator<string,array{int}>
   *   A total a determinate bar cannot be drawn from.
   */
  public static function dataProviderNonPositiveStepsAreRejected(): \Iterator {
    // Zero is the boundary the rule turns on, so it is stated beside the
    // negative rather than left to the one that is obviously out.
    yield 'no steps at all' => [0];
    yield 'fewer than none' => [-1];
  }

  public function testActivatingDeterminateRowRunsTheWorkAndCollectsNoAnswer(): void {
    $steps = 0;
    $work = function (ProgressReporter $reporter) use (&$steps): void {
      for ($index = 1; $index <= 3; $index++) {
        $steps++;
        $reporter->advance('packed ' . $index);
      }
    };

    // Drill into the panel, then activate the progress row.
    $tester = (new TuiTester($this->form($work, 3)))->rows(12);
    $answers = $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertSame(3, $steps);
    $this->assertFalse($answers->has('apply'));
    // The label the work set through advance() shows beside the bar.
    $this->assertStringContainsString('packed 3', $tester->display());
  }

  public function testActivatingIndeterminateRowTicksLikeSpinner(): void {
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

  public function testActivatingRowWithoutWorkIsNoOp(): void {
    $form = Form::create('Apply')->panel('prep', 'Prep', function (PanelBuilder $p): void {
      $p->progress('apply', 'Apply')->steps(3);
    });

    $answers = (new TuiTester($form))->rows(12)->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertFalse($answers->has('apply'));
  }

  public function testHeadlessCollectionOmitsProgressRow(): void {
    $answers = (new Tui($this->form($this->work(), 3)))->collect('{}');

    $this->assertFalse($answers->has('apply'));
  }

  public function testTheAnswerSchemaOmitsProgressRow(): void {
    $form = Form::create('Apply')->panel('prep', 'Prep', function (PanelBuilder $p): void {
      $p->text('name', 'Name');
      $p->progress('apply', 'Apply')->steps(1)->work($this->work());
    })->root();

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
      $block = $p->progress('apply', 'Apply')->work($work);

      if ($steps !== NULL) {
        $block->steps($steps);
      }
    });
  }

  /**
   * A single-step work closure, for cases that never actually run it.
   *
   * @return \Closure
   *   The work.
   */
  protected function work(): \Closure {
    return static function (ProgressReporter $reporter): void {
      $reporter->advance();
    };
  }

}
