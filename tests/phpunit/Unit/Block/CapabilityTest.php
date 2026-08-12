<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Block;

use DrevOps\PhpTui\Block\Actions;
use DrevOps\PhpTui\Block\Breadcrumb;
use DrevOps\PhpTui\Block\Capability\ActivateCapableInterface;
use DrevOps\PhpTui\Block\Capability\BindCapableInterface;
use DrevOps\PhpTui\Block\Capability\BindCapableTrait;
use DrevOps\PhpTui\Block\Capability\CaptureCapableInterface;
use DrevOps\PhpTui\Block\Capability\CollectCapableInterface;
use DrevOps\PhpTui\Block\Capability\ConstrainCapableInterface;
use DrevOps\PhpTui\Block\Capability\DependCapableInterface;
use DrevOps\PhpTui\Block\Capability\DependCapableTrait;
use DrevOps\PhpTui\Block\Capability\DescendCapableInterface;
use DrevOps\PhpTui\Block\Capability\FocusCapableInterface;
use DrevOps\PhpTui\Block\Capability\FocusCapableTrait;
use DrevOps\PhpTui\Block\Capability\OverlayCapableInterface;
use DrevOps\PhpTui\Block\Capability\RejectCapableInterface;
use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Block\Legend;
use DrevOps\PhpTui\Block\Markup;
use DrevOps\PhpTui\Block\Panel;
use DrevOps\PhpTui\Block\Progress;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Condition\ConditionInterface;
use DrevOps\PhpTui\Input\Key;
use DrevOps\PhpTui\Input\KeyMapManager;
use DrevOps\PhpTui\Input\KeyName;
use DrevOps\PhpTui\Primitive\ProgressReporter;
use DrevOps\PhpTui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests what each block can do: the capabilities it claims, and what they do.
 */
#[CoversClass(Actions::class)]
#[CoversClass(Field::class)]
#[CoversClass(Panel::class)]
#[CoversClass(Progress::class)]
#[CoversTrait(BindCapableTrait::class)]
#[CoversTrait(DependCapableTrait::class)]
#[CoversTrait(FocusCapableTrait::class)]
#[Group('block')]
final class CapabilityTest extends TestCase {

  /**
   * The namespace every block capability is declared in.
   */
  protected const string CAPABILITIES = 'DrevOps\\PhpTui\\Block\\Capability\\';

  #[DataProvider('dataProviderBlockClaimsExactlyWhatItIsGranted')]
  public function testBlockClaimsExactlyWhatItIsGranted(string $block, array $granted): void {
    // Which level owns a capability is the whole model, so a block claiming one
    // it was not granted is as wrong as a block missing one it was.
    $capability = static fn(string $interface): bool => str_starts_with($interface, self::CAPABILITIES);
    $claimed = array_values(array_filter(class_implements($block) ?: [], $capability));

    sort($claimed);
    sort($granted);

    $this->assertSame($granted, $claimed);
  }

  public static function dataProviderBlockClaimsExactlyWhatItIsGranted(): \Iterator {
    yield 'panel' => [
      Panel::class,
      [
        BindCapableInterface::class,
        DependCapableInterface::class,
        DescendCapableInterface::class,
        FocusCapableInterface::class,
        OverlayCapableInterface::class,
      ],
    ];

    yield 'field' => [
      Field::class,
      [
        BindCapableInterface::class,
        CaptureCapableInterface::class,
        CollectCapableInterface::class,
        ConstrainCapableInterface::class,
        DependCapableInterface::class,
        FocusCapableInterface::class,
        RejectCapableInterface::class,
      ],
    ];

    yield 'markup' => [Markup::class, [DependCapableInterface::class]];
    yield 'breadcrumb' => [Breadcrumb::class, []];
    yield 'legend' => [Legend::class, []];
    yield 'actions' => [Actions::class, [ActivateCapableInterface::class, FocusCapableInterface::class, RejectCapableInterface::class]];
    yield 'progress' => [Progress::class, [ActivateCapableInterface::class, DependCapableInterface::class, FocusCapableInterface::class]];
  }

  #[DataProvider('dataProviderCursorMovesOntoBlockAndOffAgain')]
  public function testCursorMovesOntoBlockAndOffAgain(FocusCapableInterface $block): void {
    $this->assertFalse($block->isFocused());
    $this->assertTrue($block->focus()->isFocused());
    $this->assertFalse($block->blur()->isFocused());
  }

  public static function dataProviderCursorMovesOntoBlockAndOffAgain(): \Iterator {
    yield 'panel' => [new Panel('delivery', 'Delivery')];
    yield 'field' => [new Field('courier', 'Courier')];
    yield 'actions' => [new Actions()];
    yield 'progress' => [new Progress('packing', 'Packing crates')];
  }

  #[DataProvider('dataProviderBlockIsThereUnlessItsConditionSaysOtherwise')]
  public function testBlockIsThereUnlessItsConditionSaysOtherwise(DependCapableInterface $block): void {
    $this->assertTrue($block->isActive());
    $this->assertFalse($block->when(static fn(): bool => FALSE)->isActive());
  }

  public static function dataProviderBlockIsThereUnlessItsConditionSaysOtherwise(): \Iterator {
    yield 'panel' => [new Panel('certification', 'Certification')];
    yield 'field' => [new Field('certifier', 'Certifier')];
    yield 'markup' => [new Markup('certified', 'Organic crates need current certification.')];
    yield 'progress' => [new Progress('packing', 'Packing crates')];
  }

  #[DataProvider('dataProviderBlockSitsWhereTheChainLeadingToItPutsIt')]
  public function testBlockSitsWhereTheChainLeadingToItPutsIt(DependCapableInterface $block): void {
    // Where a block sits is worked out once the whole tree is finished and
    // written back, so a block on its own is always at the frame edge.
    $this->assertSame(0, $block->nesting());
    $this->assertSame(2, $block->nest(2)->nesting());

    // A chain cannot run backwards, so nothing puts a block past the edge.
    $this->assertSame(0, $block->nest(-1)->nesting());
  }

  public static function dataProviderBlockSitsWhereTheChainLeadingToItPutsIt(): \Iterator {
    yield 'panel' => [new Panel('certification', 'Certification')];
    yield 'field' => [new Field('certifier', 'Certifier')];
    yield 'markup' => [new Markup('certified', 'Organic crates need current certification.')];
    yield 'progress' => [new Progress('packing', 'Packing crates')];
  }

  public function testBlockHandsBackWhatDecidesWhetherItIsThere(): void {
    $declared = new Condition('organic', eq: TRUE);
    $block = new Markup('certified', 'Organic crates need current certification.');

    $this->assertNull($block->condition());
    $this->assertSame($declared, $block->when($declared)->condition());
  }

  #[DataProvider('dataProviderConditionIsAnsweredAgainstTheAnswersSoFar')]
  public function testConditionIsAnsweredAgainstTheAnswersSoFar(DependCapableInterface $block, \Closure|ConditionInterface $when, array $answers, bool $active): void {
    $this->assertSame($active, $block->when($when)->isActive($answers));
  }

  public static function dataProviderConditionIsAnsweredAgainstTheAnswersSoFar(): \Iterator {
    $declared = new Condition('organic', eq: TRUE);
    $written = static fn(array $answers): bool => ($answers['organic'] ?? FALSE) === TRUE;
    // A condition that asks for nothing is answered without them, which is why
    // both shapes are declared the same way.
    $standing = static fn(): bool => FALSE;

    yield 'panel, declared' => [new Panel('certification', 'Certification'), $declared, ['organic' => TRUE], TRUE];
    yield 'panel, declared and unmet' => [new Panel('certification', 'Certification'), $declared, ['organic' => FALSE], FALSE];
    yield 'field, declared' => [new Field('certifier', 'Certifier'), $declared, ['organic' => TRUE], TRUE];
    yield 'field, declared and unmet' => [new Field('certifier', 'Certifier'), $declared, ['organic' => FALSE], FALSE];
    yield 'field, written' => [new Field('certifier', 'Certifier'), $written, ['organic' => TRUE], TRUE];
    yield 'field, written and unmet' => [new Field('certifier', 'Certifier'), $written, [], FALSE];
    yield 'markup, declared' => [new Markup('certified', 'Certification is current.'), $declared, ['organic' => TRUE], TRUE];
    yield 'markup, written and unmet' => [new Markup('certified', 'Certification is current.'), $written, ['organic' => FALSE], FALSE];
    yield 'progress, declared' => [new Progress('packing', 'Packing crates'), $declared, ['organic' => TRUE], TRUE];
    yield 'progress, asking for nothing' => [new Progress('packing', 'Packing crates'), $standing, ['organic' => TRUE], FALSE];
  }

  public function testBlockBindsWhateverItsScopeResolvesAndNothingElse(): void {
    // A block lists no keys of its own: what it binds is what its scope
    // resolves, so retuning one is a declaration about the form rather than
    // about every block that draws it.
    $panel = (new Panel('delivery', 'Delivery'))->enter();

    $this->assertTrue($panel->binds(Key::named(KeyName::Up)));
    $this->assertTrue($panel->binds(Key::named(KeyName::Enter)));
    $this->assertTrue($panel->binds(Key::char('?')));
    $this->assertFalse($panel->binds(Key::char('x')));
  }

  public function testNestedPanelTakesNoKeyUntilYouHaveGoneIntoIt(): void {
    $panel = new Panel('advanced', 'Advanced');

    $this->assertFalse($panel->binds(Key::named(KeyName::Up)));
    $this->assertTrue($panel->enter()->binds(Key::named(KeyName::Up)));
    $this->assertFalse($panel->leave()->binds(Key::named(KeyName::Up)));
  }

  public function testBlockAnswersToTheBindingsItIsGiven(): void {
    $keys = KeyMapManager::create('vim');
    $panel = (new Panel('delivery', 'Delivery'))->enter();

    $this->assertFalse($panel->binds(Key::char('j')));

    $this->assertTrue($panel->bind($keys)->binds(Key::char('j')));
    $this->assertSame($keys->navigation(), $panel->bindings());
  }

  public function testOpenFieldBindsEveryPrintableKeyAndClosedOneBindsNone(): void {
    $field = new Field('courier', 'Courier');

    // The same key stops at an open field and travels outward from a closed
    // one, which is one rule rather than an exception written for the help key.
    $this->assertFalse($field->binds(Key::char('?')));
    $this->assertFalse($field->binds(Key::named(KeyName::Enter)));
    $this->assertTrue($field->open()->binds(Key::char('?')));
    $this->assertTrue($field->binds(Key::named(KeyName::Enter)));
    $this->assertFalse($field->close()->binds(Key::char('?')));
  }

  public function testOpenFieldTakesPrintableKeyOnlyWhereItsKindTakesTypedInput(): void {
    $basket = (new Field('basket', 'Basket contents', FieldType::Select))->option('apple', 'Apple')->open();

    // A single choice is walked with the cursor rather than typed into, so a
    // printable key is not something being typed and travels outward.
    $this->assertFalse($basket->binds(Key::char('x')));
    $this->assertTrue($basket->binds(Key::named(KeyName::Down)));
  }

  public function testOpenFieldAdvertisesItsEditorsKeysAndClosedOneAdvertisesNone(): void {
    $field = new Field('courier', 'Courier');

    $this->assertSame([], $field->hints());
    $this->assertEquals($field->open()->editor()?->hints(), $field->hints());
    $this->assertSame($field->editor()?->keys(), $field->bindings());
  }

  public function testActivatingActionsPressesTheButtonTheCursorRestsOn(): void {
    $actions = (new Actions())->action('submit', 'Submit')->action('cancel', 'Cancel');

    $this->assertSame('submit', $actions->selected());
    $this->assertNull($actions->activated());

    $this->assertTrue($actions->select('cancel')->activate());
    $this->assertSame('cancel', $actions->activated());
  }

  public function testWithheldSubmitLeavesTheButtonUnpressed(): void {
    $actions = (new Actions())->action('submit', 'Submit')->refuse('Basket contents is required.');

    $this->assertFalse($actions->activate());
    $this->assertNull($actions->activated());

    $this->assertTrue($actions->refuse(NULL)->activate());
    $this->assertSame('submit', $actions->activated());
  }

  public function testActionsWithNoButtonsHaveNothingToPress(): void {
    $this->assertFalse((new Actions())->activate());
  }

  public function testActivatingProgressRunsItsWork(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(4);

    $progress->work(static function (ProgressReporter $reporter): void {
      $reporter->advance();
      $reporter->advance();
    });

    $this->assertTrue($progress->activate());
    $this->assertStringContainsString('2/4', $progress->render(new DefaultTheme(80, ['color' => FALSE])));
  }

  public function testProgressWithNoWorkDoesNothingWhenActivated(): void {
    $this->assertFalse((new Progress('packing', 'Packing crates'))->activate());
  }

  public function testWorkSaysWhatItIsDoingAsItAdvances(): void {
    $progress = (new Progress('packing', 'Packing crates'))->steps(4);

    $progress->work(static function (ProgressReporter $reporter): void {
      $reporter->advance('Apples');
      $reporter->advance();
    });

    $this->assertTrue($progress->activate());
    $this->assertSame(2, $progress->current());
    $this->assertSame(4, $progress->total());
    // A step that says nothing leaves the label where the last one set it.
    $this->assertSame('Apples', $progress->labelText());
    $this->assertSame('Packing crates', $progress->caption());
  }

}
