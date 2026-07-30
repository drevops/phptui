<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Block;

use DrevOps\Tui\Block\Actions;
use DrevOps\Tui\Block\Breadcrumb;
use DrevOps\Tui\Block\Capability\ActivateCapableInterface;
use DrevOps\Tui\Block\Capability\BindCapableInterface;
use DrevOps\Tui\Block\Capability\BindCapableTrait;
use DrevOps\Tui\Block\Capability\CaptureCapableInterface;
use DrevOps\Tui\Block\Capability\CollectCapableInterface;
use DrevOps\Tui\Block\Capability\ConstrainCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Capability\DescendCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableInterface;
use DrevOps\Tui\Block\Capability\FocusCapableTrait;
use DrevOps\Tui\Block\Capability\OverlayCapableInterface;
use DrevOps\Tui\Block\Capability\RejectCapableInterface;
use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Legend;
use DrevOps\Tui\Block\Markup;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Progress;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Theme\DefaultTheme;
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
  protected const string CAPABILITIES = 'DrevOps\\Tui\\Block\\Capability\\';

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
      [BindCapableInterface::class, DescendCapableInterface::class, FocusCapableInterface::class, OverlayCapableInterface::class],
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
    yield 'field' => [new Field('certifier', 'Certifier')];
    yield 'markup' => [new Markup('certified', 'Organic crates need current certification.')];
    yield 'progress' => [new Progress('packing', 'Packing crates')];
  }

  public function testBlockBindsTheKeysItWasGivenAndNoOthers(): void {
    $panel = (new Panel('delivery', 'Delivery'))->bind(KeyName::Up, KeyName::Down);

    $this->assertSame([KeyName::Up, KeyName::Down], $panel->bindings());
    $this->assertTrue($panel->binds(Key::named(KeyName::Up)));
    $this->assertFalse($panel->binds(Key::named(KeyName::Escape)));
    $this->assertFalse($panel->binds(Key::char('?')));
  }

  public function testKeyBoundTwiceIsStillOneBinding(): void {
    $panel = (new Panel('delivery', 'Delivery'))->bind(KeyName::Up)->bind(KeyName::Down, KeyName::Up);

    $this->assertSame([KeyName::Up, KeyName::Down], $panel->bindings());
  }

  public function testOpenFieldBindsEveryPrintableKeyAndClosedOneBindsNone(): void {
    $field = (new Field('courier', 'Courier'))->bind(KeyName::Enter);

    // The same key stops at an open field and travels outward from a closed
    // one, which is one rule rather than an exception written for the help key.
    $this->assertFalse($field->binds(Key::char('?')));
    $this->assertTrue($field->open()->binds(Key::char('?')));
    $this->assertTrue($field->binds(Key::named(KeyName::Enter)));
    $this->assertFalse($field->close()->binds(Key::char('?')));
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

    $progress->work(static function (Progress $block): void {
      $block->advance(2);
    });

    $this->assertTrue($progress->activate());
    $this->assertStringContainsString('2/4', $progress->render(new DefaultTheme(80, ['color' => FALSE])));
  }

  public function testProgressWithNoWorkDoesNothingWhenActivated(): void {
    $this->assertFalse((new Progress('packing', 'Packing crates'))->activate());
  }

}
