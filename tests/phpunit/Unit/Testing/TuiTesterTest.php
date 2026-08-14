<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Testing;

use DrevOps\PhpTui\Answers\Provenance;
use DrevOps\PhpTui\Builder\Form;
use DrevOps\PhpTui\Builder\PanelBuilder;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Discovery\Dotenv;
use DrevOps\PhpTui\Discovery\JsonValue;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Testing\TuiTester;
use DrevOps\PhpTui\Theme\Border;
use DrevOps\PhpTui\Theme\Spacing;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the form-level scripted-keystroke harness.
 */
#[CoversClass(TuiTester::class)]
#[Group('testing')]
final class TuiTesterTest extends TestCase {

  public function testRunWithKeysAndStrings(): void {
    $tester = new TuiTester($this->form());

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'Ada',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Escape),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertSame('Ada', $answers->value('name'));
    $this->assertSame('Ada', $tester->answers()->value('name'));
    $this->assertFalse($tester->isCancelled());
    $this->assertStringContainsString('Ada', $tester->display());
    $this->assertStringContainsString('Ada', $tester->output());
  }

  public function testRunWithRawByteSequences(): void {
    $tester = new TuiTester($this->form());

    // "\r" is Enter, "\033" is Escape, "\033[B" is Down.
    $answers = $tester->run("\r", "\r", 'Bo', "\r", "\033", "\033[B", "\r");

    $this->assertSame('Bo', $answers->value('name'));
    $this->assertFalse($tester->isCancelled());
  }

  public function testRunEnforcesHandlerBehaviourWhileEditing(): void {
    $form = Form::create('Demo')
      ->panel('Stall', 'stall', static function (PanelBuilder $p): void {
        $p->text('Machine name', 'machine_name');
      });

    $tester = new TuiTester($form, ['DrevOps\PhpTui\Tests\Fixtures\Handler']);

    // The handler's static validate() rejects the empty accept inline, then the
    // typed value is accepted and lowercased by its static transform().
    $answers = $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter), Key::named(KeyName::Enter), 'ABC', Key::named(KeyName::Enter));

    $this->assertStringContainsString('A machine name is required.', $tester->display());
    $this->assertSame('abc', $answers->value('machine_name'));
  }

  public function testCancelButtonIsReported(): void {
    $tester = new TuiTester($this->form());

    // Root rows: the panel, then the pair of buttons on one row of their own -
    // so Down reaches them and Right walks along them to Cancel.
    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Right), Key::named(KeyName::Enter));

    $this->assertTrue($tester->isCancelled());
  }

  public function testInterruptIsReported(): void {
    $tester = new TuiTester($this->form());

    // Ctrl-C mid-form aborts the loop: interrupted, not a cancel-button finish.
    $tester->run(Key::named(KeyName::Interrupt));

    $this->assertTrue($tester->isInterrupted());
    $this->assertFalse($tester->isCancelled());
  }

  public function testAbortedRunStillHandsBackWhatWasAnsweredAndDrawn(): void {
    $tester = new TuiTester($this->form());

    // A session that ends without a submit raises rather than returning, and
    // the harness answers the question instead of passing the raise on.
    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'Ada',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Interrupt),
    );

    $this->assertSame('Ada', $answers->value('name'));
    $this->assertStringContainsString('Ada', $tester->display());
    $this->assertTrue($tester->isInterrupted());
  }

  public function testEndingIsForgottenBetweenOneRunAndTheNext(): void {
    $tester = new TuiTester($this->form());

    $tester->run(Key::named(KeyName::Interrupt));
    $this->assertTrue($tester->isInterrupted());

    $tester->run();
    $this->assertFalse($tester->isInterrupted());
    $this->assertFalse($tester->isCancelled());
  }

  public function testEmptyRunCollectsDefaults(): void {
    $answers = (new TuiTester($this->form()))->run();

    $this->assertSame('', $answers->value('name'));
  }

  public function testConditionalFieldSurfacesWithDefault(): void {
    $form = Form::create('Demo')
      ->panel('Packing', 'packing', function (PanelBuilder $p): void {
        $p->confirm('Extra', 'extra')->default(FALSE);
        $p->text('Notes', 'notes')->default('mixed')->when(new Condition('extra'));
      });

    // Drill in, flip the gate on, back out, submit: the surfaced field carries
    // its declared default even though it was inactive when the session began.
    $answers = (new TuiTester($form))->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'y',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Escape),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertTrue($answers->value('extra'));
    $this->assertSame('mixed', $answers->value('notes'));
  }

  public function testFluentSettersConfigureTheRun(): void {
    $tester = (new TuiTester($this->form()))
      ->theme('default')
      ->options(['color' => TRUE])
      ->rows(30)
      ->version('1.2.3')
      ->directory('somewhere');

    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertSame('', $answers->value('name'));
    // The colour option flowed through: the captured output carries ANSI.
    $this->assertStringContainsString("\033[", $tester->output());
  }

  public function testUpdateModePreFillsDetectedValues(): void {
    vfsStream::setup('project', NULL, [
      'box.json' => '{"name": "Weekly Box"}',
      '.env' => 'SEASON=winter',
    ]);
    $dir = vfsStream::url('project');

    // Update on: discovery pre-fills the panels, each detected value badged
    // "detected" and shown once the panel is open.
    $on = (new TuiTester($this->discoveryForm()))->directory($dir)->update();
    $answers = $on->run(Key::named(KeyName::Enter));

    $this->assertSame('Weekly Box', $answers->value('name'));
    $this->assertSame('winter', $answers->value('season'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('name'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('season'));
    $this->assertStringContainsString('Weekly Box', $on->display());

    // Update off (the harness default): discovery never runs, so the plain
    // declared defaults surface instead.
    $off = (new TuiTester($this->discoveryForm()))->directory($dir);
    $answers = $off->run(Key::named(KeyName::Enter));

    $this->assertSame('', $answers->value('name'));
    $this->assertSame('summer', $answers->value('season'));
    $this->assertSame(Provenance::Default, $answers->provenanceOf('season'));
  }

  public function testUpdateModeReBadgesEditedDetectedValue(): void {
    vfsStream::setup('project', NULL, [
      'box.json' => '{"name": "Weekly Box"}',
      '.env' => 'SEASON=winter',
    ]);
    $dir = vfsStream::url('project');

    $tester = (new TuiTester($this->discoveryForm()))->directory($dir)->update();

    // Drill in, edit the detected "name" (its buffer seeds with the detected
    // value and lands the cursor at the end, so typing appends), accept, back
    // out, then submit.
    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      ' XL',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Escape),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    // The edited field re-badges detected -> edited; the untouched one keeps
    // its detected badge.
    $this->assertSame('Weekly Box XL', $answers->value('name'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('name'));
    $this->assertSame('winter', $answers->value('season'));
    $this->assertSame(Provenance::Detected, $answers->provenanceOf('season'));
  }

  /**
   * Every result accessor guards against being read before run().
   *
   * @param \Closure $access
   *   Reads one result accessor off a tester.
   */
  #[DataProvider('dataProviderAccessorBeforeRunThrows')]
  public function testAccessorBeforeRunThrows(\Closure $access): void {
    $this->expectException(\LogicException::class);

    $access(new TuiTester($this->form()));
  }

  /**
   * Data provider for testAccessorBeforeRunThrows().
   *
   * @return \Iterator<string,array{\Closure}>
   *   One accessor closure per case.
   */
  public static function dataProviderAccessorBeforeRunThrows(): \Iterator {
    yield 'answers' => [static fn(TuiTester $tester): mixed => $tester->answers()];
    yield 'output' => [static fn(TuiTester $tester): mixed => $tester->output()];
    yield 'is cancelled' => [static fn(TuiTester $tester): mixed => $tester->isCancelled()];
    yield 'is interrupted' => [static fn(TuiTester $tester): mixed => $tester->isInterrupted()];
  }

  public function testFullscreenLaysOutToTheScriptedColumns(): void {
    $tester = new TuiTester($this->form());

    // No input: one frame renders, stretched to the scripted terminal's rows
    // and laid out to its columns - a border pads every line to the full
    // width, so both dimensions are assertable.
    $tester->options(['fullscreen' => TRUE, 'color' => FALSE, 'border' => Border::Line, 'spacing' => Spacing::Normal])->rows(12)->cols(48)->run();

    $lines = explode("\n", $tester->display());
    $this->assertCount(12, $lines);

    foreach ($lines as $line) {
      $this->assertSame(48, mb_strlen($line, 'UTF-8'));
    }
  }

  public function testColsRejectsNonPositiveWidth(): void {
    $this->expectException(\InvalidArgumentException::class);

    (new TuiTester($this->form()))->cols(0);
  }

  public function testModalSubmitFlowsThroughTheInputPipe(): void {
    $tester = new TuiTester($this->modalForm());

    // Open the modal from the hub (Down to the modal item, Enter), edit its
    // field (Enter, type, Enter), Apply it (Down, Enter), then submit the form
    // (Down to Submit, Enter).
    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'Zed',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertSame('Zed', $answers->value('nick'));
    $this->assertFalse($tester->isCancelled());
  }

  public function testModalDiscardRestoresThroughTheInputPipe(): void {
    $tester = new TuiTester($this->modalForm());

    // Same as the submit flow but Discard the dialog (Right along its buttons,
    // Enter) instead of Apply, so the edit is rolled back before the form
    // submits.
    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'Zed',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Right),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    );

    $this->assertSame('', $answers->value('nick'));
  }

  /**
   * A single-field form used across the harness tests.
   */
  protected function form(): Form {
    return Form::create('Demo')
      ->panel('Main', 'main', function (PanelBuilder $p): void {
        $p->text('Name', 'name');
      });
  }

  /**
   * A form whose second top-level panel is a modal dialog.
   */
  protected function modalForm(): Form {
    return Form::create('Demo')
      ->panel('Main', 'main', function (PanelBuilder $p): void {
        $p->text('Name', 'name');
      })
      ->panel('Quick edit', 'edit', function (PanelBuilder $m): void {
        $m->modal('Apply', 'Discard');
        $m->text('Nickname', 'nick');
      });
  }

  /**
   * A form whose fields discover their defaults from the project directory.
   */
  protected function discoveryForm(): Form {
    return Form::create('Update')
      ->panel('Box', 'box', function (PanelBuilder $p): void {
        $p->text('Box name', 'name')->default('')->discover(new JsonValue('box.json', 'name'));
        $p->text('Season', 'season')->default('summer')->discover(new Dotenv('SEASON'));
      });
  }

}
