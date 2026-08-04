<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Screen;

use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Prose;
use DrevOps\Tui\Builder\Form;
use DrevOps\Tui\Builder\PanelBuilder;
use DrevOps\Tui\Condition\Condition;
use DrevOps\Tui\Derive\Derive;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\InterruptException;
use DrevOps\Tui\Model\Buttons;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Fixup;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Render\ExternalEditor;
use DrevOps\Tui\Render\Terminal;
use DrevOps\Tui\Screen\Collector;
use DrevOps\Tui\Screen\KeyRouter;
use DrevOps\Tui\Screen\Layout\GridLayout;
use DrevOps\Tui\Screen\Layout\LayoutInterface;
use DrevOps\Tui\Screen\Layout\PanelLayout;
use DrevOps\Tui\Screen\ScreenController;
use DrevOps\Tui\Screen\ScreenRenderer;
use DrevOps\Tui\Testing\ScreenTester;
use DrevOps\Tui\Tests\Traits\ResetsTranslatorTrait;
use DrevOps\Tui\Theme\DosTheme;
use DrevOps\Tui\Theme\Spacing;
use DrevOps\Tui\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a screen session behaves the way the documented one does.
 *
 * The form logic settling after every answer, dialogs, the editor handoff, a
 * field with the frame to itself, the chrome around the form, and the sessions
 * a consumer configures - each read as what somebody in front of the terminal
 * would see rather than as what any one class does.
 */
#[CoversClass(ScreenController::class)]
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(KeyRouter::class)]
#[CoversClass(Collector::class)]
#[CoversClass(Prose::class)]
#[CoversClass(ScreenTester::class)]
#[CoversClass(Field::class)]
#[CoversClass(Markup::class)]
#[CoversTrait(DependCapableTrait::class)]
#[Group('screen')]
final class ScreenParityTest extends TestCase {

  use ResetsTranslatorTrait;

  public function testDependentRowAppearsTheMomentItsConditionHolds(): void {
    $panel = $this->panel(
      new Markup('intro', 'Pick the produce.'),
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('organic', eq: TRUE)),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(Key::named(KeyName::Enter), Key::char('y'), Key::named(KeyName::Enter));

    // The row is not there while the answer it depends on says it is not, and
    // is there on the very next frame once the answer changes.
    $this->assertStringNotContainsString('Certifier', $tester->frame(0));
    $this->assertStringContainsString('Certifier', $tester->frame());
    $this->assertSame('Soil Board', $answers->value('certifier'));
  }

  public function testRowThatLeavesTakesTheCursorOffItself(): void {
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('organic', eq: TRUE)),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
      Key::char('n'),
      Key::named(KeyName::Enter),
    );

    $this->assertStringNotContainsString('Certifier', $tester->frame());
    $this->assertFalse($answers->has('certifier'));
  }

  public function testSectionAppearsTheMomentItsConditionHolds(): void {
    $certification = $this->nested('certification', 'Certification', (new Field('certifier', 'Certifier'))->default('Soil Board'));
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      $certification->when(new Condition('organic', eq: TRUE)),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(Key::named(KeyName::Enter), Key::char('y'), Key::named(KeyName::Enter));

    // A whole section comes and goes exactly as one row does, and what it holds
    // reaches the answers only once it is there.
    $this->assertStringNotContainsString('Certification', $tester->frame(0));
    $this->assertStringContainsString('Certification', $tester->frame());
    $this->assertSame('Soil Board', $answers->value('certifier'));
  }

  public function testSectionThatLeavesTakesTheCursorOntoRowThatIsThere(): void {
    $certification = $this->nested('certification', 'Certification', (new Field('certifier', 'Certifier'))->default('Soil Board'));
    $panel = $this->panel(
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      $certification->when(new Condition('organic', eq: TRUE)),
    );

    $tester = $this->tester($panel);
    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
      Key::char('n'),
      Key::named(KeyName::Enter),
    );

    $this->assertStringNotContainsString('Certification', $tester->frame());
    $this->assertStringContainsString('❯ Organic only?', $tester->frame());
    $this->assertFalse($answers->has('certifier'));
  }

  public function testSectionThatGoesWhileYouAreInsideItPutsYouBackOutsideIt(): void {
    $form = Form::create('Delivery')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->confirm('organic', 'Organic only?')->default(TRUE);

        $p->panel('certification', 'Certification', static function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier');
        });
      });

    // The answer that decides the section is written by a rule rather than
    // typed, which is how an answer given inside a section can take it away.
    $fixup = new Fixup(set: 'organic', to: FALSE, when: new Condition('certifier', eq: 'none'));
    $tester = (new ScreenTester($form->root()))->rows(14)->cols(60)->collector(new Collector(NULL, [$fixup]));
    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      'none',
      Key::named(KeyName::Enter),
    );

    // The section is where the reader was standing, so it cannot simply stop
    // being drawn: the way out is the way in, as far as the nearest section
    // that is still there.
    $this->assertStringContainsString('Delivery › Produce order', $tester->frame());
    $this->assertStringNotContainsString('Certifier', $tester->frame());
    $this->assertStringContainsString('Organic only?', $tester->frame());
    $this->assertFalse($answers->has('certifier'));
  }

  public function testSectionTheAnswersRemovedIsDealtNoWindowEither(): void {
    $form = Form::create('Market stall')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->layout(2);
        $p->confirm('organic', 'Organic only?')->default(FALSE);

        $p->panel('fruit', 'Fruit', static function (PanelBuilder $sp): void {
          $sp->select('fruit', 'Fruit')->default('apple')->options(['apple' => 'Apple', 'pear' => 'Pear']);
        });

        $p->panel('certification', 'Certification', static function (PanelBuilder $sp): void {
          $sp->when(new Condition('organic', eq: TRUE));
          $sp->text('certifier', 'Certifier')->default('Soil Board');
        });
      });

    $tester = (new ScreenTester($form->root()))->rows(16)->cols(70);
    $tester->run(Key::named(KeyName::Enter));

    // A window is how a section draws where its siblings sit beside it, so a
    // section that is not there is dealt none and the row closes up.
    $this->assertStringContainsString('Fruit', $tester->frame());
    $this->assertStringNotContainsString('Certification', $tester->frame());
    $this->assertStringNotContainsString('Certifier', $tester->frame());
  }

  public function testComputedAnswerRecomputesAsTheAnswerItReadsChanges(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley'),
      (new Field('label', 'Crate label'))->derive(new Derive('{{courier}}')),
    );

    $answers = $this->tester($panel)->run(Key::named(KeyName::Enter), ' Runs', Key::named(KeyName::Enter));

    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertSame('Valley Runs', $answers->value('label'));
    $this->assertSame(Provenance::Derived, $answers->provenanceOf('label'));
  }

  public function testRuleThatWritesAnAnswerReAppliesAfterEveryEdit(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('label', 'Crate label'))->default('none'),
    );

    $tester = $this->tester($panel)->collector(new Collector(NULL, [new Fixup(set: 'label', from: 'courier')]));
    $answers = $tester->run(Key::named(KeyName::Enter), '!', Key::named(KeyName::Enter));

    $this->assertSame('Valley Runs!', $answers->value('label'));
  }

  public function testRowSetThatFollowsTheAnswersNarrowsBeforeTheNextFrame(): void {
    $catalog = [
      'fruit' => ['apple' => 'Apple', 'pear' => 'Pear'],
      'vegetable' => ['carrot' => 'Carrot', 'tomato' => 'Tomato'],
    ];

    $category = (new Field('category', 'Category', FieldType::Select))
      ->default('fruit')
      ->entry('fruit', 'Fruit')
      ->entry('vegetable', 'Vegetable');

    $item = (new Field('item', 'Item', FieldType::Select))->resolve(static function (Context $context) use ($catalog): array {
      $category = $context->answers['category'] ?? '';

      return is_string($category) ? ($catalog[$category] ?? []) : [];
    });

    $tester = $this->tester($this->panel($category, $item));

    $answers = $tester->run(
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
    );

    // The set the second row offers followed the first row's new answer, so
    // what opened under it is what the new category holds.
    $this->assertStringContainsString('Carrot', $tester->frame(-2));
    $this->assertSame('vegetable', $answers->value('category'));
    $this->assertSame('carrot', $answers->value('item'));
  }

  public function testTakingTheAnswerThatWasAlreadyThereStillRecordsIt(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')));
    $answers = $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertSame(Provenance::Edited, $answers->provenanceOf('courier'));
    $this->assertStringContainsString('edited', $tester->frame());
  }

  public function testModalPanelOpensAsDialogOverTheScreenItWasOpenedFrom(): void {
    $tester = $this->tester($this->order())->rows(16);
    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $frame = $tester->frame();

    // The screen behind is still there, with the dialog drawn over it: its own
    // title, its standing text, its rows and its own way out.
    $this->assertStringContainsString('Item  Pear', $frame);
    $this->assertStringContainsString('│ Gift options', $frame);
    $this->assertStringContainsString('│ Wrap this order as a gift.', $frame);
    $this->assertStringContainsString('[ Save ]  [ Discard ]', $frame);
  }

  public function testDialogKeepsWhatItCollectedWhenItsOwnSubmitClosesIt(): void {
    $tester = $this->tester($this->order())->rows(16);
    $answers = $tester->run(...$this->intoTheDialog(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('Enjoy!', $answers->value('note'));
    $this->assertStringNotContainsString('[ Save ]', $tester->frame());
  }

  public function testDialogPutsTheAnswersBackWhenItsOwnCancelClosesIt(): void {
    $tester = $this->tester($this->order())->rows(16);

    $answers = $tester->run(...$this->intoTheDialog(
      Key::named(KeyName::Down),
      Key::named(KeyName::Right),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('Enjoy', $answers->value('note'));
  }

  public function testDialogPutsTheAnswersBackWhenItIsAbandonedInstead(): void {
    $tester = $this->tester($this->order())->rows(16);

    $this->assertSame('Enjoy', $tester->run(...$this->intoTheDialog(Key::named(KeyName::Escape)))->value('note'));
  }

  public function testLeavingInsideDialogClosesTheDialogRatherThanTheForm(): void {
    $tester = $this->tester($this->order())->rows(16);
    $answers = $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter), Key::char('q'), Key::named(KeyName::Up));

    // The form ran on after the dialog closed, so the key after it drew a
    // frame of the panel rather than ending the session.
    $this->assertStringNotContainsString('[ Save ]', $tester->frame());
    $this->assertSame('Pear', $answers->value('item'));
  }

  public function testTextareaHandsItsBufferToTheEditorOfTheReadersOwn(): void {
    $editor = new EditorFixture(TRUE);
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))->default('Weighed')->externalEditor();

    $tester = $this->tester($this->panel($notes))->externalEditor($editor);
    $answers = $tester->run(Key::named(KeyName::Enter), Key::char("\x05"), Key::named(KeyName::Tab));

    // The session left the terminal to the editor and took it back, and what
    // came back is what the row now holds.
    $this->assertTrue($notes->hasHandoff());
    $this->assertSame('Weighed at the bench', $answers->value('notes'));
    $this->assertInstanceOf(Terminal::class, $editor->suspended);
  }

  public function testWhatComesBackFromTheEditorIsMeasuredLikeAnythingTyped(): void {
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))
      ->default('Weighed')
      ->externalEditor()
      ->validate(static fn(mixed $value): ?string => $value === 'Weighed at the bench' ? 'Say where, not what.' : NULL);

    $tester = $this->tester($this->panel($notes))->externalEditor(new EditorFixture(TRUE));
    $answers = $tester->run(Key::named(KeyName::Enter), Key::char("\x05"), Key::named(KeyName::Tab));

    // Text written somewhere else is still an offer: the row measures it and
    // stays open on it, exactly as it does with text typed into the frame.
    $this->assertStringContainsString('Say where, not what.', $tester->frame());
    $this->assertSame('Weighed', $answers->value('notes'));
  }

  public function testFieldOffersNoHandoffWhereThereIsNoEditorToHandOffTo(): void {
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))->default('Weighed')->externalEditor();

    $tester = $this->tester($this->panel($notes))->externalEditor(new EditorFixture(FALSE))->cols(90);
    $tester->run(Key::named(KeyName::Enter));

    $this->assertFalse($notes->hasHandoff());
    $this->assertStringContainsString('to accept', $tester->frame());
    $this->assertStringNotContainsString('CTRL', $tester->frame());
  }

  public function testHelpTakesThePageItNeedsAndDrawsWhatItExplains(): void {
    $courier = (new Field('courier', 'Courier'))->help("Every crate is weighed at the **packing bench**.\n\n- crates go out at noon");

    $tester = $this->tester($this->panel($courier))->options(['markdown' => TRUE]);
    $tester->run(Key::char('?'), Key::named(KeyName::Down));

    // The page names the row it belongs to and draws the passage as prose, and
    // the next key puts the panel back.
    $this->assertStringContainsString('Courier', $tester->frame(1));
    $this->assertStringContainsString('Every crate is weighed at the packing bench.', $tester->frame(1));
    $this->assertStringContainsString('• crates go out at noon', $tester->frame(1));
    $this->assertStringNotContainsString('crates go out at noon', $tester->frame(2));
  }

  public function testStandaloneFieldTakesTheWholeFrameAndComesBackOnCancel(): void {
    $harvest = (new Field('harvest', 'Harvest date', FieldType::Calendar))->default('2026-07-15')->standalone();
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs'), $harvest))->rows(16);

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter), Key::named(KeyName::Escape));

    // Nothing but the field is in front of the reader while it is open, and
    // the panel is back the moment it closes.
    $this->assertStringContainsString('July 2026', $tester->frame(2));
    $this->assertStringNotContainsString('Courier', $tester->frame(2));
    $this->assertStringContainsString('Courier  Valley Runs', $tester->frame(3));
  }

  public function testBannerIsShownBeforeTheFormAndAnyKeyDismissesIt(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->banner('ORCHARD', '1.2.3');
    $tester->run(Key::named(KeyName::Enter));

    $this->assertStringContainsString('ORCHARD', $tester->frame(0));
    $this->assertStringContainsString('Version: 1.2.3', $tester->frame(0));
    $this->assertStringContainsString('Press any key to continue...', $tester->frame(0));
    $this->assertStringContainsString('Courier', $tester->frame(1));
  }

  public function testInterruptAtTheBannerAbortsRatherThanOpeningTheForm(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->banner('ORCHARD');

    try {
      $tester->run(Key::named(KeyName::Interrupt));
    }
    catch (InterruptException) {
      // The abort is what the assertion below is about.
    }

    $this->assertCount(1, $tester->frames());
    $this->assertStringContainsString('ORCHARD', $tester->frame());
  }

  public function testTerminalTooSmallForTheFrameSaysSoAndTakesOnlyTheKeyThatLeaves(): void {
    $weight = new Field('weight', 'Basket weight');
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $weight))
      ->rows(6)->cols(24)
      ->options(['fullscreen' => TRUE, 'min_width' => 40, 'min_height' => 10]);

    $tester->run(Key::named(KeyName::Down), Key::char('q'));

    $this->assertStringContainsString('Terminal too small.', $tester->frame());
    $this->assertStringContainsString('Need at least 40 x 10 - have 24 x 6.', $tester->frame());

    // Nothing behind the notice moved, because nothing behind it was reachable.
    $this->assertFalse($weight->isFocused());
  }

  public function testTerminalWideEnoughForTheFormItselfPassesTheGuard(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('certifier', 'Certifier'))->default('Soil Board')->when(new Condition('courier', eq: 'Coast Runs')),
      $this->nested('advanced', 'Advanced', new Field('grade', 'Grade')),
    );

    // No minimum was stated, so the guard measures the rows the form draws -
    // the rows that are not there and the panels it can be walked into aside.
    $tester = $this->tester($panel)
      ->rows(12)->cols(40)
      ->options(['fullscreen' => TRUE]);

    $tester->run();

    $this->assertStringNotContainsString('Terminal too small.', $tester->frame());
    $this->assertStringContainsString('Courier  Valley Runs', $tester->frame());
  }

  public function testFullscreenFrameIsAnchoredWhereTheThemeSaysIt(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))
      ->rows(12)->cols(50)
      ->options(['fullscreen' => TRUE, 'halign' => 'center', 'valign' => 'middle', 'max_width' => 20, 'max_height' => 4]);

    $tester->run();

    $lines = explode("\n", $tester->frame());

    // The frame is capped to the size the theme allows and floats in the
    // middle of the terminal, padded with blank rows on every side.
    $this->assertCount(12, $lines);
    $this->assertSame('', trim($lines[0]));
    $this->assertSame('               Delivery', rtrim($lines[4]));
  }

  public function testTerminalIsWashedWithTheBackgroundTheThemeDeclares(): void {
    $tester = $this->tester($this->panel(new Field('courier', 'Courier')))->rows(6)->cols(30)->theme(new DosTheme(30, ['color' => TRUE]));
    $tester->run();

    $this->assertStringContainsString("\033[44m", $tester->output());
  }

  public function testCompactSpacingDropsTheExplanationUnderTheRow(): void {
    $courier = (new Field('courier', 'Courier'))->description('The run this basket goes out on.');

    $padded = $this->tester($this->panel($courier))->options(['spacing' => Spacing::Normal]);
    $padded->run(Key::named(KeyName::Enter));

    $compact = $this->tester($this->panel($courier))->options(['spacing' => Spacing::Compact]);
    $compact->run(Key::named(KeyName::Enter));

    $this->assertStringContainsString('The run this basket goes out on.', $padded->frame());
    $this->assertStringNotContainsString('The run this basket goes out on.', $compact->frame());
  }

  #[DataProvider('dataProviderSpacingDecidesWhatShowsBetweenTheRows')]
  public function testSpacingDecidesWhatShowsBetweenTheRows(Spacing $spacing, array $expected): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('weight', 'Basket weight'))->default('1200'),
    );

    $tester = $this->tester($panel)->options(['spacing' => $spacing]);
    $tester->run();

    $rows = array_map(rtrim(...), array_slice(explode("\n", $tester->frame()), 1, count($expected)));

    $this->assertSame($expected, $rows);
  }

  public static function dataProviderSpacingDecidesWhatShowsBetweenTheRows(): \Iterator {
    // The padded spacing is what a form gets without asking for one, so its
    // blank row between two answers is the shape a reader meets by default.
    yield 'padded' => [Spacing::Padded, ['❯ Courier  Valley Runs', '', '  Basket weight  1200', '', '[ Submit ]  [ Cancel ]']];
    yield 'normal' => [Spacing::Normal, ['❯ Courier  Valley Runs', '  Basket weight  1200', '[ Submit ]  [ Cancel ]']];
    yield 'compact' => [Spacing::Compact, ['❯ Courier  Valley Runs', '  Basket weight  1200', '[ Submit ]  [ Cancel ]']];
  }

  #[DataProvider('dataProviderSettledRowReadsTheAnswerRatherThanHoldsIt')]
  public function testSettledRowReadsTheAnswerRatherThanHoldsIt(Field $field, string $reads): void {
    $tester = $this->tester($this->panel($field));
    $tester->run();

    // The row under the trail is the field's, so what it reads is the whole of
    // what the answer says on screen.
    $this->assertSame($reads, rtrim(explode("\n", $tester->frame())[1]));
  }

  public static function dataProviderSettledRowReadsTheAnswerRatherThanHoldsIt(): \Iterator {
    yield 'a decision is a word' => [
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(TRUE),
      '❯ Organic only?  yes',
    ];

    yield 'a decision against is one too' => [
      (new Field('organic', 'Organic only?', FieldType::Confirm))->default(FALSE),
      '❯ Organic only?  no',
    ];

    yield 'a secret never prints' => [
      (new Field('key', 'Orchard key', FieldType::Password))->default('winter-pear'),
      '❯ Orchard key  ••••••••',
    ];

    yield 'an unanswered secret masks nothing' => [
      (new Field('key', 'Orchard key', FieldType::Password))->default(''),
      '❯ Orchard key',
    ];

    yield 'several answers read as one run' => [
      (new Field('basket', 'Basket contents', FieldType::Select))
        ->multiple()
        ->entry('apple', 'Apple')
        ->entry('carrot', 'Carrot')
        ->default(['apple', 'carrot']),
      '❯ Basket contents  apple, carrot',
    ];

    yield 'a grade reads as its scale' => [
      (new Field('ripeness', 'Ripeness', FieldType::Rating))->bounds(new NumberBounds(1, 5))->captions([3 => 'Ready'])->default(3),
      '❯ Ripeness  ●●●○○ 3/5 Ready',
    ];

    yield 'a weight is its number' => [
      (new Field('weight', 'Basket weight', FieldType::Number))->default(1200),
      '❯ Basket weight  1200',
    ];

    yield 'a date is the day it names' => [
      (new Field('harvest', 'Harvest date', FieldType::Calendar))->default('2026-07-15'),
      '❯ Harvest date  2026-07-15',
    ];

    yield 'a filled shape is the shape' => [
      (new Field('crate', 'Crate code', FieldType::Template))->pattern(new Template('{{orchard}}-{{fruit}}'))->default('valley-apple'),
      '❯ Crate code  valley-apple',
    ];
  }

  public function testAnswerThatCarriesLineBreaksTakesOneRowPerLine(): void {
    $notes = (new Field('notes', 'Packing notes', FieldType::Textarea))->default("Weighed at the bench\r\nSealed at dawn");

    $tester = $this->tester($this->panel($notes));
    $tester->run();

    $rows = array_map(rtrim(...), array_slice(explode("\n", $tester->frame()), 1, 2));

    // No row ever carries a newline of its own, and the line that follows lines
    // up under the value column rather than under the label.
    $this->assertSame('❯ Packing notes  Weighed at the bench', $rows[0]);
    $this->assertSame('                 Sealed at dawn', $rows[1]);
  }

  public function testMarkdownDrawsTheSubsetInTheRowExplanation(): void {
    $courier = (new Field('courier', 'Courier'))->description('Pick what is **ripe** today.');

    $on = $this->tester($this->panel($courier))->options(['markdown' => TRUE, 'color' => TRUE]);
    $on->run(Key::named(KeyName::Enter));

    $off = $this->tester($this->panel($courier))->options(['color' => TRUE]);
    $off->run(Key::named(KeyName::Enter));

    // The markers are drawn rather than shown where markdown is on, and left
    // exactly as they were typed where it is not.
    $this->assertStringContainsString('Pick what is ripe today.', $on->display());
    $this->assertStringContainsString("\033[1mripe", $on->output());
    $this->assertStringContainsString('Pick what is **ripe** today.', $off->display());
  }

  public function testMarkdownDrawsTheSubsetInStandingNote(): void {
    $note = new Markup('intro', "Pick what is **ripe**:\n- crisp apples");

    $tester = $this->tester($this->panel($note))->options(['markdown' => TRUE]);
    $tester->run();

    $this->assertStringContainsString('Pick what is ripe:', $tester->frame());
    $this->assertStringContainsString('• crisp apples', $tester->frame());
  }

  public function testLinkResolvesWhetherOrNotMarkdownIsDrawn(): void {
    $note = new Markup('intro', 'The [seasonal guide](https://example.com/guide) lists them.');

    $tester = $this->tester($this->panel($note));
    $tester->run();

    // Colour is off here, so the address is kept rather than hidden behind a
    // label no terminal could open.
    $this->assertStringContainsString('The seasonal guide (https://example.com/guide)', $tester->frame());
  }

  public function testRowThatOnlyShowsNeverTakesTheCursor(): void {
    $courier = new Field('courier', 'Courier');
    $weight = new Field('weight', 'Basket weight');
    $panel = $this->panel($courier, new Markup('intro', 'Pick the produce.'), $weight);

    $this->tester($panel)->run(Key::named(KeyName::Down));

    $this->assertTrue($weight->isFocused());
    $this->assertFalse($courier->isFocused());
  }

  public function testLeavingEndsTheSessionWithTheAnswersAsTheyStand(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')));
    $answers = $tester->run(Key::char('q'), Key::named(KeyName::Down));

    // Leaving is not abandoning, so the answers stand - and the key after it
    // drew no frame, because the session had ended.
    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertCount(1, $tester->frames());
  }

  public function testGoingIntoPanelReplacesTheScreenWithItsContents(): void {
    $advanced = $this->nested('advanced', 'Advanced', new Field('certifier', 'Certifier'), new Field('grade', 'Grade'));
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $advanced))->rows(14);

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $inside = $tester->frame();

    // What the panel holds is the whole of what is in front of the reader: the
    // row it was entered from, the rows beside it and the buttons that end the
    // form are all left behind, and only the trail says where the cursor is.
    $this->assertStringContainsString('Delivery › Advanced', $inside);
    $this->assertStringContainsString('Certifier', $inside);
    $this->assertStringContainsString('Grade', $inside);
    $this->assertStringNotContainsString('Courier', $inside);
    $this->assertStringNotContainsString('[ Submit ]', $inside);

    // Everything fits, so nothing says there is more to reach.
    $this->assertStringNotContainsString('▼', $inside);
  }

  public function testComingBackOutOfPanelDrawsEverythingItReplaced(): void {
    $advanced = $this->nested('advanced', 'Advanced', new Field('certifier', 'Certifier'));
    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $advanced))->rows(14);

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter), Key::named(KeyName::Escape));

    $back = $tester->frame();

    $this->assertStringContainsString('Courier', $back);
    $this->assertStringContainsString('Advanced', $back);
    $this->assertStringContainsString('[ Submit ]', $back);
    $this->assertStringNotContainsString('Certifier', $back);
  }

  public function testVimPresetDrivesTheWholeSession(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley'),
      (new Field('weight', 'Basket weight'))->default('12'),
    );

    $tester = $this->tester($panel)->keys(KeyMapManager::create('vim'));

    $answers = $tester->run(
      Key::char('j'),
      Key::named(KeyName::Enter),
      '0',
      Key::named(KeyName::Enter),
      Key::char('k'),
      Key::named(KeyName::Enter),
      ' Runs',
      Key::named(KeyName::Enter),
      Key::char('j'),
      Key::char('j'),
      Key::named(KeyName::Enter),
    );

    $this->assertSame('Valley Runs', $answers->value('courier'));
    $this->assertSame('120', $answers->value('weight'));
  }

  public function testRowSetThatHasToBeFetchedIsFetchedWhenItsPanelOpens(): void {
    $calls = 0;
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))->load(function () use (&$calls): array {
      $calls++;

      return ['apple' => 'Apple', 'carrot' => 'Carrot'];
    });

    $tester = $this->tester($this->nested('main', 'Delivery', $basket));
    $tester->run(Key::named(KeyName::Enter));

    // Asked once, by the session opening the panel that holds it - and the row
    // says the set is still coming rather than reading as empty until it lands.
    $this->assertSame(1, $calls);
    $this->assertStringContainsString('Apple', $tester->frame());
  }

  public function testRowSetIsFetchedOnlyOnceTheDeeperPanelIsWalkedInto(): void {
    $calls = 0;
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))->load(function () use (&$calls): array {
      $calls++;

      return ['apple' => 'Apple'];
    });

    $tester = $this->tester($this->panel(new Field('courier', 'Courier'), $this->nested('advanced', 'Advanced', $basket)));
    $tester->run();

    // Nobody has walked into it, so nothing has paid for it yet.
    $this->assertSame(0, $calls);

    $tester->run(Key::named(KeyName::Down), Key::named(KeyName::Enter));

    $this->assertSame(1, $calls);
  }

  public function testLegendAdvertisesTheWayOutOfTheFormButNotOutOfAnOpenRow(): void {
    $tester = $this->tester($this->panel((new Field('courier', 'Courier'))->default('Valley Runs')))->cols(80);
    $tester->run(Key::named(KeyName::Enter));

    // Leaving is about the session, so it is offered where it acts - and while
    // a row is open the same letter is something being typed.
    $this->assertStringContainsString('to quit', $tester->frame(0));
    $this->assertStringNotContainsString('to quit', $tester->frame());
  }

  public function testLegendOffersHelpOnlyWhereThereIsHelpToShow(): void {
    $courier = (new Field('courier', 'Courier'))->help('Every crate is weighed at the packing bench.');
    $tester = $this->tester($this->panel($courier, new Field('weight', 'Basket weight')))->cols(80);
    $tester->run(Key::named(KeyName::Down));

    $this->assertStringContainsString('to show help', $tester->frame(0));
    $this->assertStringNotContainsString('to show help', $tester->frame());
  }

  public function testNestedPanelsSitSideBySideWhereTheFormArrangesThemThatWay(): void {
    $fruit = $this->nested('fruit', 'Fruit', (new Field('fruit', 'Fruit'))->default('Apple'));
    $veg = $this->nested('veg', 'Vegetables', (new Field('veg', 'Vegetables'))->default('Carrot'));
    $panel = $this->dealt([2], (new Field('name', 'Order name'))->default('Weekly Box'), $fruit, $veg);

    $tester = $this->tester($panel)->cols(60);
    $tester->run();

    $rows = array_map(rtrim(...), explode("\n", $tester->frame()));

    // Each window previews the panel behind it - the way in, then its own rows
    // - and the two share a row rather than following one another.
    $this->assertContains('  Fruit ›                        Vegetables ›', $rows);
    $this->assertContains('  Fruit  Apple                   Vegetables  Carrot', $rows);
  }

  public function testGridDealsItsPanelsIntoTheVisualRowsTheFormDeclares(): void {
    $panel = $this->dealt(
      [1, 2],
      $this->nested('summary', 'Summary', (new Field('name', 'Order name'))->default('Weekly Box')),
      $this->nested('fruit', 'Fruit', (new Field('fruit', 'Fruit'))->default('Apple')),
      $this->nested('veg', 'Vegetables', (new Field('veg', 'Vegetables'))->default('Carrot')),
    );

    $tester = $this->tester($panel)->rows(16)->cols(60);
    $tester->run();

    $rows = array_map(rtrim(...), explode("\n", $tester->frame()));

    // One full-width window above two sharing the row below it.
    $this->assertContains('❯ Summary ›', $rows);
    $this->assertContains('  Fruit ›                        Vegetables ›', $rows);
  }

  public function testCursorMovesSpatiallyAcrossTheGridOfWindows(): void {
    $panel = $this->dealt(
      [1, 2],
      $this->nested('summary', 'Summary', (new Field('name', 'Order name'))->default('Weekly Box')),
      $this->nested('fruit', 'Fruit', (new Field('fruit', 'Fruit'))->default('Apple')),
      $this->nested('veg', 'Vegetables', (new Field('veg', 'Vegetables'))->default('Carrot')),
    );

    $tester = $this->tester($panel)->rows(16)->cols(60);

    // Down leaves the full-width window for the row beneath it, right walks
    // that row, and up comes back to the window above rather than to the
    // window the cursor passed on the way down.
    $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Right),
      Key::named(KeyName::Up),
    );

    $frames = array_map(static fn(string $frame): array => array_map(rtrim(...), explode("\n", $frame)), $tester->frames());

    $this->assertContains('  Fruit ›                        Vegetables ›', $frames[0]);
    $this->assertContains('❯ Fruit ›                        Vegetables ›', $frames[1]);
    $this->assertContains('  Fruit ›                      ❯ Vegetables ›', $frames[2]);
    $this->assertContains('❯ Summary ›', $frames[3]);

    // A grid is moved through in two directions, so the legend says so.
    $this->assertStringContainsString('←/→', $tester->frame());
  }

  public function testRowPackedFromTheEndOfFlowIsStillOneYouLandOn(): void {
    $panel = $this->panel((new Field('courier', 'Courier'))->default('Valley Runs'));
    $panel->in('content')->tail((new Field('weight', 'Basket weight'))->default('1200'));

    $tester = $this->tester($panel);
    $answers = $tester->run(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      '!',
      Key::named(KeyName::Enter),
    );

    // Which end it was packed from says where it is drawn and nothing else, so
    // the cursor reaches it in the order it is drawn in - after the buttons
    // that end the form, which the session puts among the panel's own rows.
    $this->assertStringContainsString('Basket weight', $tester->frame());
    $this->assertSame('1200!', $answers->value('weight'));
  }

  public function testPanelRowCountsThePicksItHasNoRoomToList(): void {
    $form = Form::create('Order')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->select('basket', 'Basket')->multiple()->default(['apple', 'beet', 'carrot', 'date'])
          ->options(['apple' => 'Apple', 'beet' => 'Beet', 'carrot' => 'Carrot', 'date' => 'Date']);
        $p->select('herbs', 'Herbs')->multiple()->default(['basil', 'dill'])
          ->options(['basil' => 'Basil', 'dill' => 'Dill']);
      });

    $tester = (new ScreenTester($form->root()))->rows(10)->cols(70);
    $tester->run();

    // The line standing for a whole panel spells out a handful of picks and
    // says how many there were once there are more than a handful.
    $this->assertStringContainsString('4 items selected', $tester->frame());
    $this->assertStringContainsString('basil, dill', $tester->frame());
  }

  public function testChainOfConditionsStepsInOneStepPerLink(): void {
    $form = Form::create('Conditional indentation')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->select('category', 'Category')->default('vegetable')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable']);
        // One condition deep: it hangs off the unconditional category above.
        $p->select('basket', 'Basket')->multiple()->default(['carrot'])->options(['carrot' => 'Carrot', 'potato' => 'Potato'])->when(new Condition('category', eq: 'vegetable'));
        // Two deep: its rule names a field that is itself conditional.
        $p->confirm('weekly', 'Weekly delivery?')->default(TRUE)->when(new Condition('basket', contains: 'carrot'));
        // Three deep, and the steps keep going for as long as the chain does.
        $p->text('courier', 'Courier note')->default('Leave at the gate')->when(new Condition('weekly', eq: TRUE));
        // Unconditional again, so the row returns to the frame edge.
        $p->number('quantity', 'Quantity')->min(1)->max(99)->default(6);
      });

    $stepped = (new ScreenTester($form->root()))->rows(16)->cols(70)->options(['indent_conditional' => TRUE]);
    // Into the panel, so its own rows are what is in front of the reader.
    $stepped->run(Key::named(KeyName::Enter));

    $rows = array_map(rtrim(...), explode("\n", $stepped->frame()));

    // One step per link in the chain, and the row after it back at the edge.
    $this->assertContains('❯ Category  vegetable', $rows);
    $this->assertContains('    Basket  carrot', $rows);
    $this->assertContains('      Weekly delivery?  yes', $rows);
    $this->assertContains('        Courier note  Leave at the gate', $rows);
    $this->assertContains('  Quantity  6', $rows);
  }

  public function testChainOfConditionsStaysFlushUntilTheThemeIsAskedToStepIt(): void {
    $form = Form::create('Conditional indentation')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->select('category', 'Category')->default('vegetable')->options(['fruit' => 'Fruit', 'vegetable' => 'Vegetable']);
        $p->confirm('weekly', 'Weekly delivery?')->default(TRUE)->when(new Condition('category', eq: 'vegetable'));
      });

    $flush = (new ScreenTester($form->root()))->rows(12)->cols(70);
    $flush->run(Key::named(KeyName::Enter));

    // Off by default: a conditional row renders exactly where every other one
    // does, so nothing on screen says which answer brought it into view.
    $this->assertContains('  Weekly delivery?  yes', array_map(rtrim(...), explode("\n", $flush->frame())));
  }

  #[DataProvider('dataProviderEveryKindOfRowInChainStepsInWithIt')]
  public function testEveryKindOfRowInChainStepsInWithIt(array $options, array $expected): void {
    $form = Form::create('Conditional indentation')
      ->panel('order', 'Produce order', static function (PanelBuilder $p): void {
        $p->confirm('weekly', 'Weekly delivery?')->default(TRUE);
        // One condition, three kinds of row: whatever a rule can take off the
        // form steps in behind the answer that brought it back.
        $p->text('courier', 'Courier note')->default('Leave at the gate')->when(new Condition('weekly', eq: TRUE));
        $p->markup('crates', 'Weekly crates are packed the evening before.')->when(new Condition('weekly', eq: TRUE));
        $p->text('gate', 'Gate code')->default('4821')->when(new Condition('weekly', eq: TRUE));
      });

    $tester = (new ScreenTester($form->root()))->rows(16)->cols(70)->options($options);
    $tester->run(Key::named(KeyName::Enter));

    $rows = array_map(rtrim(...), explode("\n", $tester->frame()));

    foreach ($expected as $row) {
      $this->assertContains($row, $rows);
    }
  }

  public static function dataProviderEveryKindOfRowInChainStepsInWithIt(): \Iterator {
    yield 'stepped' => [
      ['indent_conditional' => TRUE],
      [
        '❯ Weekly delivery?  yes',
        '    Courier note  Leave at the gate',
        '  Weekly crates are packed the evening before.',
        '    Gate code  4821',
      ],
    ];

    yield 'flush' => [
      [],
      [
        '❯ Weekly delivery?  yes',
        '  Courier note  Leave at the gate',
        'Weekly crates are packed the evening before.',
        '  Gate code  4821',
      ],
    ];
  }

  public function testFormThatHidesItsLegendAdvertisesNothing(): void {
    $shown = $this->tester($this->panel(new Field('courier', 'Courier')));
    $shown->run();

    $hidden = $this->tester($this->panel(new Field('courier', 'Courier')))->footer(FALSE);
    $hidden->run();

    $this->assertStringContainsString('to select', $shown->frame());
    $this->assertStringNotContainsString('to select', $hidden->frame());
    $this->assertStringContainsString('Courier', $hidden->frame());
  }

  public function testLocalizedSessionDrawsItsChromeInTheActiveLanguage(): void {
    Translator::setShared(new Translator('uk'));

    $tester = $this->tester($this->nested('main', 'Постачання', (new Field('courier', 'Кур\'єр'))->default('Coast Runs')));
    $tester->run(Key::named(KeyName::Enter), Key::named(KeyName::Enter));

    // The chrome the library speaks and the words the form declares, both in
    // the language the run was given.
    $this->assertStringContainsString('Постачання', $tester->frame());
    $this->assertStringContainsString('[ Надіслати ]  [ Скасувати ]', $tester->frame());
    $this->assertStringContainsString('перемістити', $tester->frame());
    $this->assertStringContainsString('змінено', $tester->frame());
  }

  public function testUpdateModeBadgesTheAnswersItDetected(): void {
    $courier = (new Field('courier', 'Courier'))->discover(static fn(Context $context): string => 'Runs from ' . $context->directory);

    $tester = $this->tester($this->panel($courier))->context(new Context('/orchard', [], TRUE));
    $answers = $tester->run();

    $this->assertSame(Provenance::Detected, $answers->provenanceOf('courier'));
    $this->assertSame('detected', $courier->badgeText());
    $this->assertStringContainsString('Courier  Runs from /orchard', $tester->frame());
    $this->assertStringContainsString(' detected ', $tester->frame());
  }

  public function testBadgeSitsInItsOwnColumnAtTheEdgeOfTheFrame(): void {
    $panel = $this->panel(
      (new Field('courier', 'Courier'))->default('Valley Runs'),
      (new Field('grade', 'Grade'))->default('Premium hand-picked'),
    );

    $tester = $this->tester($panel)->supplied(['courier' => 'Coast Runs', 'grade' => 'Standard']);
    $tester->run();

    $rows = array_values(array_filter(explode("\n", $tester->frame()), static fn(string $row): bool => str_contains($row, 'edited')));

    // Every row of a frame is as wide as the frame, so a badge that ends where
    // the row does is one in a column of its own - and two of them there line
    // up with each other rather than trailing answers of different lengths.
    $this->assertCount(2, $rows);
    $this->assertStringEndsWith(' edited ', $rows[0]);
    $this->assertStringEndsWith(' edited ', $rows[1]);
  }

  /**
   * The keys that open the dialog, change its row, then whatever follows.
   *
   * @return list<string|\DrevOps\Tui\Input\Key>
   *   The scripted keys.
   */
  protected function intoTheDialog(string|Key ...$then): array {
    return array_values([
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
      Key::named(KeyName::Enter),
      '!',
      Key::named(KeyName::Enter),
      ...$then,
    ]);
  }

  /**
   * A form whose second row opens a dialog collecting one answer.
   */
  protected function order(): Panel {
    $gift = $this->nested('gift', 'Gift options', (new Field('note', 'Gift message'))->default('Enjoy'));
    $gift->description('Wrap this order as a gift.')->buttons(new Buttons(TRUE, 'Save', 'Discard'))->modal();

    return $this->nested('main', 'Basket', (new Field('item', 'Item'))->default('Pear'), $gift);
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
    return $this->arranged(new PanelLayout(), $id, $title, ...$blocks);
  }

  /**
   * The panel a screen starts in, arranged as a grid of windows.
   */
  protected function dealt(array $shape, object ...$blocks): Panel {
    return $this->arranged(new GridLayout(...$shape), 'main', 'Delivery', ...$blocks);
  }

  /**
   * A panel holding the given blocks, arranged by a layout of its own.
   */
  protected function arranged(LayoutInterface $layout, string $id, string $title, object ...$blocks): Panel {
    $panel = (new Panel($id, $title))->layout($layout);

    foreach ($blocks as $block) {
      /** @var \DrevOps\Tui\Block\BlockInterface $block */
      $panel->in('content')->add($block);
    }

    return $panel;
  }

}

/**
 * An editor of the reader's own that answers without launching anything.
 */
final class EditorFixture extends ExternalEditor {

  /**
   * The terminal the session left to the editor, once it did.
   */
  public ?Terminal $suspended = NULL;

  /**
   * Construct the fixture.
   *
   * @param bool $available
   *   Whether there is an editor to hand off to at all.
   */
  public function __construct(protected bool $available) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function isAvailable(): bool {
    return $this->available;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function edit(string $initial, ?Terminal $terminal = NULL): string {
    $this->suspended = $terminal;

    return $initial . ' at the bench';
  }

}
