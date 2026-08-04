<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\CancelException;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FilePickerConstraints;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Primitive\ProgressReporter;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\TerminalControl;
use DrevOps\Tui\Screen\Axis;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\Layout\AbstractLayout;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use DrevOps\Tui\Screen\Layout\TwoColumnLayout;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Testing\BufferedTerminal;
use DrevOps\Tui\Testing\ScreenTester;
use DrevOps\Tui\Theme\Border;
use DrevOps\Tui\Theme\DefaultTheme;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests driving a screen: one key in, one frame out, until the form ends.
 */
#[CoversClass(ScreenController::class)]
#[Group('screen')]
final class ScreenControllerTest extends TestCase {

  public function testFormOpensOnTheAnswersHeadlessCollectionWouldArriveAt(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('weight', 'Basket weight', FieldType::Number))->default(1200),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run();

    $this->assertStringContainsString('Courier  Valley Runs', $tester->frame(0));
    $this->assertStringContainsString('Basket weight  1200', $tester->frame(0));
    $this->assertSame((new Collector())->collect($this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('weight', 'Basket weight', FieldType::Number))->default(1200),
    )), ['courier' => $answers->value('courier'), 'weight' => $answers->value('weight')]);
  }

  public function testSuppliedValueIsWhatTheFormOpensOn(): void {
    $panel = $this->panel((new Field('courier', 'Courier'))->default('Valley Runs'));

    $tester = $this->tester($panel)->supplied(['courier' => 'Coast Runs']);
    $answers = $tester->run();

    $this->assertStringContainsString('Courier  Coast Runs', $tester->frame(0));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('courier'));
  }

  public function testFieldItsConditionHidesContributesNoAnswer(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('organic', eq: TRUE)),
    );

    $this->assertFalse($this->tester($panel)->run()->has('certifier'));
  }

  public function testCursorWalksTheRowsThatTakeItAndSkipsTheRest(): void {
    $courier = new Field('courier', 'Courier');
    $weight = new Field('weight', 'Basket weight');
    $panel = $this->panel($courier, new Markup('weighing', 'Weighed at the bench.'), $weight);

    $this->tester($panel)->run(Key::named(KeyName::Down));

    $this->assertTrue($weight->isFocused());
    $this->assertFalse($courier->isFocused());
  }

  public function testEnterOpensTheFieldAndTheKeysOnOfferBecomeItsOwn(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));
    $tester->run(Key::named(KeyName::Enter));

    $this->assertStringContainsString('to select', $tester->frame(0));
    $this->assertStringContainsString('to accept', $tester->frame(1));
    $this->assertStringNotContainsString('to select', $tester->frame(1));
  }

  public function testTypingReachesAnOpenFieldAndAcceptingTakesIt(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    $answers = $tester->run(Key::named(KeyName::Enter), 'Coast', Key::named(KeyName::Enter));

    $this->assertSame('Coast', $answers->value('courier'));
  }

  public function testTheListKeysReachAnOpenListRatherThanMovingBetweenRows(): void {
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))
      ->multiple()
      ->entry('apple', 'Apple')
      ->entry('carrot', 'Carrot');

    $tester = $this->tester($this->panel($basket, new Field('courier', 'Courier')));

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    );

    $this->assertSame(['apple', 'carrot'], $answers->value('basket'));
  }

  public function testRefusedValueLeavesTheFieldOpenWithTheReasonOnIt(): void {
    $courier = (new Field('courier', 'Courier'))
      ->default('Valley Runs')
      ->validate(static fn(mixed $value): ?string => $value === 'Coast' ? NULL : 'Only the coast run is taking crates.');

    $tester = $this->tester($this->panel($courier));
    $answers = $tester->run(Key::named(KeyName::Enter), 'X', Key::named(KeyName::Enter));

    // What was offered is still in front of the reader, with the reason under
    // it, and the answer stayed where it was.
    $this->assertStringContainsString('Only the coast run is taking crates.', $tester->frame());
    $this->assertSame('Only the coast run is taking crates.', $courier->refusal());
    $this->assertSame('Valley Runs', $answers->value('courier'));
  }

  public function testRefusedValueIsTakenOnceItIsAcceptable(): void {
    $courier = (new Field('courier', 'Courier'))
      ->validate(static fn(mixed $value): ?string => $value === 'Coast' ? NULL : 'Only the coast run is taking crates.');

    $tester = $this->tester($this->panel($courier));

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      'X',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Backspace),
      'Coast',
      Key::named(KeyName::Enter),
    );

    $this->assertSame('Coast', $answers->value('courier'));
    $this->assertNull($courier->refusal());
  }

  public function testFieldRefusesTheNumberItsBoundsRuleOutAndStaysOpenOnIt(): void {
    $weight = (new Field('weight', 'Basket weight', FieldType::Number))->default(1200)->bounds(new NumberBounds(200, 9000));
    $tester = $this->tester($this->panel($weight));

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Backspace),
      Key::named(KeyName::Backspace),
      Key::named(KeyName::Backspace),
      Key::named(KeyName::Backspace),
      '5',
      Key::named(KeyName::Enter),
    );

    $this->assertStringContainsString('Enter a number between 200 and 9000.', $tester->frame());
    // The row is still open on what was typed, so it can be corrected in place.
    $this->assertStringContainsString('Basket weight  5', $tester->frame());
    $this->assertSame(1200, $answers->value('weight'));
  }

  public function testFieldRefusesTooFewPicksAndStaysOpenOnThem(): void {
    $basket = (new Field('basket', 'Basket', FieldType::Select))->multiple()->selections(new SelectionBounds(2))->default([]);
    $basket->entry('pear', 'Pear')->entry('plum', 'Plum');
    $tester = $this->tester($this->panel($basket));

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    );

    $this->assertStringContainsString('Select at least 2 items.', $tester->frame());
    // Said once: the reason replaces the standing guidance rather than
    // stacking under a second copy of it.
    $this->assertSame(1, substr_count($tester->frame(), 'Select at least 2 items.'));
    $this->assertSame([], $answers->value('basket'));
  }

  public function testFieldRefusesThePickItsLimitsRuleOutAndStaysOpenOnIt(): void {
    vfsStream::setup('orchard', NULL, ['prices.csv' => str_repeat('a', 200)]);
    $manifest = (new Field('manifest', 'Manifest', FieldType::FilePicker))
      ->startIn(vfsStream::url('orchard'))
      ->picker(new FilePickerConstraints(maxSize: 100))
      ->default('');
    $tester = $this->tester($this->panel($manifest));

    $answers = $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertStringContainsString('Choose a file no larger than 100 B.', $tester->frame());
    // The limit it missed replaces the standing statement of that limit.
    $this->assertStringNotContainsString('Max 100 B.', $tester->frame());
    $this->assertSame('', $answers->value('manifest'));
  }

  public function testEscapeClosesAnOpenFieldWithoutCommittingWhatWasTyped(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')));

    $answers = $tester->run(Key::named(KeyName::Enter), 'X', Key::named(KeyName::Escape));

    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertStringContainsString('Courier  Valley Runs', $tester->frame());
  }

  public function testEnterOnTheNestedPanelGoesIntoItAndTheTrailGrows(): void {
    $advanced = $this->nested('advanced', 'Advanced', new Field('certifier', 'Certifier'));
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $advanced));

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertStringContainsString('Delivery › Advanced', $tester->frame());
    $this->assertStringContainsString('Certifier', $tester->frame());
    $this->assertTrue($advanced->isEntered());
  }

  public function testEscapeComesBackOutToTheRowItWasLeftOn(): void {
    $advanced = $this->nested('advanced', 'Advanced', new Field('certifier', 'Certifier'));
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $advanced));

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter), Key::named(KeyName::Escape));

    $this->assertStringNotContainsString('› Advanced', $tester->frame());
    $this->assertTrue($advanced->isFocused());
  }

  public function testRegionScrollsToKeepTheFocusedRowInSight(): void {
    $fields = [];

    foreach (range(1, 10) as $index) {
      $fields[] = new Field('crate' . $index, 'Crate ' . $index);
    }

    $tester = $this->tester($this->panel(...$fields))->rows(8);
    $tester->run(...array_fill(0, 8, Key::named(KeyName::Down)));

    // The first frame opens on the top of the list, with a mark saying the
    // rows run past the bottom of the region.
    $this->assertStringContainsString('Crate 1', $tester->frame(0));
    $this->assertStringContainsString('▼', $tester->frame(0));

    // The last one has followed the cursor down, so the row it is on shows and
    // both edges are marked.
    $this->assertStringContainsString('Crate 9', $tester->frame());
    $this->assertStringNotContainsString('Crate 1 ', $tester->frame());
    $this->assertStringContainsString('▲', $tester->frame());
  }

  public function testSubmitIsWithheldWhileTheFieldIsOwedAnAnswer(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->required()));

    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter));

    // The button stayed unpressed, so the session ran on to the end of the
    // scripted keys rather than ending on it.
    $this->assertStringContainsString('Courier is required.', $tester->frame());
    $this->assertNull($answers->value('courier'));
  }

  public function testRowThatOnlyShowsIsNeitherCollectedNorOwedAnAnswer(): void {
    $panel = $this->panel(
      new Markup('intro', 'Pick the produce.'),
      (new Field('courier', 'Courier'))->default('Valley Runs'),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    // The row carries no answer, so nothing is owed for it and the form ended
    // on the button rather than refusing it.
    $this->assertFalse($answers->has('intro'));
    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertCount(3, $tester->frames());
  }

  public function testFieldItsConditionHidesIsNotOwedAnAnswerEither(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      (new Field('certifier', 'Certifier'))->required()->when(new Condition('organic', eq: TRUE)),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Enter));

    // A field that is not there is never asked for, so an empty one cannot
    // withhold the submit.
    $this->assertFalse($answers->has('certifier'));
    $this->assertCount(3, $tester->frames());
  }

  public function testOnlyTheRegionHoldingTheCursorFollowsIt(): void {
    $panel = (new Panel('main', 'Delivery'))->layout(new TwoScrollingRowsLayoutFixture());

    foreach (range(1, 4) as $index) {
      $panel->in('top')->add(new Field('crate' . $index, 'Crate ' . $index));
      $panel->in('bottom')->add(new Field('case' . $index, 'Case ' . $index));
    }

    $tester = $this->tester($panel)->rows(8);
    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Down), Key::named(KeyName::Down));

    // The cursor is on the last row of the top region, so that region followed
    // it while the one below stayed where it was left.
    $this->assertStringContainsString('Crate 4', $tester->frame());
    $this->assertStringContainsString('Case 1', $tester->frame());
  }

  public function testAnsweringTheFieldRetiresTheRefusalAndLetsTheSubmitThrough(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->required()));

    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
      'Coast',
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
    );

    $this->assertStringNotContainsString('is required', $tester->frame());
    $this->assertSame('Coast', $answers->value('courier'));

    // The session ended on the button, so the key after it drew no frame.
    $this->assertCount(8, $tester->frames());
  }

  public function testTheHorizontalKeysWalkTheButtonsAndCancelAbandonsTheForm(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    $this->expectException(CancelException::class);
    $this->expectExceptionMessage('The interactive session was cancelled.');

    $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Right),
      Key::named(KeyName::Left),
      Key::named(KeyName::Right),
      Key::named(KeyName::Enter),
    );
  }

  public function testTheCancelledFormLeavesItsLastFrameToBeRead(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    try {
      $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Right), Key::named(KeyName::Enter));
    }
    catch (CancelException) {
      // The frames survive the abandonment, which is what makes what was on
      // screen at the time assertable.
    }

    $this->assertStringContainsString('[ Cancel ]', $tester->frame());
  }

  public function testFormThatHidesItsButtonsDrawsNone(): void {
    $panel = $this->panel(new Field('courier', 'Courier'))->buttons(new Buttons(FALSE));

    $tester = $this->tester($panel);
    $tester->run();

    $this->assertStringNotContainsString('Submit', $tester->frame());
  }

  public function testButtonsAreLabelledAsThePanelDeclaresThem(): void {
    $panel = $this->panel(new Field('courier', 'Courier'))->buttons(new Buttons(TRUE, 'Send it', 'Forget it'));

    $tester = $this->tester($panel);
    $tester->run();

    $this->assertStringContainsString('[ Send it ]', $tester->frame());
    $this->assertStringContainsString('[ Forget it ]', $tester->frame());
  }

  public function testProgressRunsItsWorkOnActivationAndDrawsItsIndicator(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(4)->work(static function (ProgressReporter $reporter): void {
      $reporter->advance('the apples');
      $reporter->advance('the carrots');
    });

    $tester = $this->tester($this->panel($progress));
    $tester->run(Key::named(KeyName::Enter));

    // A frame per step, so the bar fills in place while the work runs.
    $this->assertStringContainsString('0/4', $tester->frame(1));
    $this->assertStringContainsString('1/4 the apples', $tester->frame(2));
    $this->assertStringContainsString('2/4 the carrots', $tester->frame(3));
  }

  public function testProgressWithNoWorkDrawsNothingNew(): void {
    $tester = $this->tester($this->panel(new Progress('packing', 'Packing crates')));
    $tester->run(Key::named(KeyName::Enter));

    // A frame before the key and a frame after it: nothing ran in between, so
    // nothing repainted the row.
    $this->assertCount(2, $tester->frames());
    $this->assertSame($tester->frame(0), $tester->frame(1));
  }

  public function testHelpKeyOpensTheFocusedFieldsHelpAndAnyKeyDismissesIt(): void {
    $courier = (new Field('courier', 'Courier'))->help('Every crate is weighed at the packing bench.');
    $tester = $this->tester($this->panel($courier));

    $tester->run(Key::char('?'), Key::named(KeyName::Down));

    $this->assertStringContainsString('Every crate is weighed at the packing bench.', $tester->frame(1));
    $this->assertStringNotContainsString('Every crate is weighed', $tester->frame(2));
  }

  public function testChangedAnswerIsStampedAsEdited(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')));

    $answers = $tester->run(Key::named(KeyName::Enter), 'X', Key::named(KeyName::Enter));

    $this->assertSame('Valley RunsX', $answers->value('courier'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('courier'));
  }

  public function testChangingComputedAnswerPinsTheRuleThatComputesIt(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('label', 'Crate label'))->derive(new Derive('courier')),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter), 'X', Key::named(KeyName::Enter));

    $this->assertSame(Provenance::Override, $answers->provenanceOf('label'));
  }

  public function testInterruptKeyAbortsTheSessionFromAnywhere(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));

    $this->expectException(InterruptException::class);
    $this->expectExceptionMessage('The interactive session was interrupted.');

    $tester->run(Key::named(KeyName::Enter), 'X', Key::named(KeyName::Interrupt));
  }

  public function testExhaustedScriptEndsTheSessionWithTheAnswersAsTheyStand(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')));

    $this->assertSame('Valley Runs', $tester->run()->value('courier'));
  }

  public function testScreenIsClearedAsTheSessionEnds(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')));
    $tester->run();

    $this->assertStringEndsWith($this->clear(), $tester->output());
  }

  public function testConsumerCanKeepTheLastFrameOnScreen(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->clearOnExit(FALSE);
    $tester->run();

    $this->assertStringEndsNotWith($this->clear(), $tester->output());
  }

  public function testAnAbortAlwaysLeavesTheScreenClean(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->clearOnExit(FALSE);

    try {
      $tester->run(Key::named(KeyName::Interrupt));
    }
    catch (InterruptException) {
      // The clear is what the assertion below is about.
    }

    $this->assertStringEndsWith($this->clear(), $tester->output());
  }

  public function testFrameIsDrawnInsideTheBorderItWasGiven(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->rows(6)->cols(30)->border(Border::Rounded);
    $tester->run();

    $lines = explode("\n", $tester->frame());

    $this->assertStringStartsWith('╭', $lines[0]);
    $this->assertStringStartsWith('╰', $lines[5]);
  }

  public function testFrameIsNeverLaidOutWiderThanTheTerminal(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->cols(40);
    $tester->run();

    foreach (explode("\n", $tester->frame()) as $line) {
      $this->assertSame(40, Ansi::width($line));
    }
  }

  public function testPanelThatNamesItsRegionsAnythingElseTakesTheButtonsInTheFirst(): void {
    $panel = (new Panel('main', 'Delivery'))->layout(new TwoColumnLayout());
    $panel->in('left')->add(new Field('courier', 'Courier'));

    $tester = $this->tester($panel);
    $tester->run();

    $this->assertStringContainsString('[ Submit ]', $tester->frame());
  }

  public function testWhatPrecedesTheFirstFrameIsDrawnBeforeIt(): void {
    $clear = $this->clear();
    $controller = new class($this->panel(new Field('courier', 'Courier')), new DefaultTheme(40, ['color' => FALSE])) extends ScreenController {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      protected function opening(): string {
        return 'Orchard deliveries';
      }

    };

    $terminal = new BufferedTerminal([], 8, 40);
    $controller->run($terminal);

    $this->assertStringStartsWith($clear . 'Orchard deliveries', $terminal->output());
  }

  public function testDrivingOneKeyDrawsNothingWithNoTerminal(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(4)->work(static function (ProgressReporter $reporter): void {
      $reporter->advance();
    });

    $controller = new ScreenController($this->panel($progress), new DefaultTheme(40, ['color' => FALSE]));
    $controller->handle(Key::named(KeyName::Enter));

    // The work still ran; only the drawing needed somewhere to draw.
    $this->assertSame(1, $progress->current());
    $this->assertFalse($controller->isCancelled());
    $this->assertFalse($controller->isInterrupted());
  }

  #[DataProvider('dataProviderFrameIsDrawnToTheWidthTheThemeStates')]
  public function testFrameIsDrawnToTheWidthTheThemeStates(Border $border, int $stated, int $columns): void {
    // Hand-built, so nothing keeps the theme and the terminal in step: the
    // theme states the room inside the frame and the frame is drawn around it,
    // whatever room the terminal happens to have.
    $theme = new DefaultTheme($stated, ['color' => FALSE, 'border' => $border->value]);
    $controller = new ScreenController($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')), $theme, border: $border);
    $terminal = new BufferedTerminal([], 8, 200);

    $controller->run($terminal);

    $drawn = explode("\n", Ansi::strip(explode($this->clear(), $terminal->output())[1] ?? ''));

    $this->assertSame($columns, Ansi::width($drawn[0]));
    $this->assertSame($columns, $theme->contentWidth() + ($border === Border::None ? 0 : 4));
  }

  /**
   * Data provider for testFrameIsDrawnToTheWidthTheThemeStates().
   *
   * @return \Iterator<string, array{\DrevOps\Tui\Theme\Border, int, int}>
   *   The frame's border, the width the theme is built for, and the columns
   *   every drawn row comes to.
   */
  public static function dataProviderFrameIsDrawnToTheWidthTheThemeStates(): \Iterator {
    yield 'no border' => [Border::None, 40, 40];
    yield 'a border, whose chrome the theme keeps out of its rows' => [Border::Line, 40, 40];
  }

  /**
   * The sequence every frame is written behind.
   *
   * @return non-empty-string
   *   The sequence.
   */
  protected function clear(): string {
    /** @var non-empty-string $clear */
    $clear = TerminalControl::clear();

    return $clear;
  }

  /**
   * A tester over a panel, sized so a frame reads the same everywhere.
   */
  protected function tester(Panel $panel): ScreenTester {
    return (new ScreenTester($panel))->rows(14)->cols(60);
  }

  /**
   * The panel a screen starts in, holding the given blocks.
   */
  protected function panel(object ...$blocks): Panel {
    return $this->nested('main', 'Delivery', ...$blocks);
  }

  /**
   * A panel holding the given blocks in its content region.
   */
  protected function nested(string $id, string $title, object ...$blocks): Panel {
    $panel = (new Panel($id, $title))->layout(new PanelLayout());

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return $panel;
  }

}

/**
 * A layout whose two rows scroll independently of each other.
 */
final class TwoScrollingRowsLayoutFixture extends AbstractLayout {

  /**
   * Construct the layout.
   */
  public function __construct() {
    parent::__construct(Axis::Rows);

    $this->region('top')->flex(1)->scrolls();
    $this->region('bottom')->flex(1)->scrolls();
  }

}
