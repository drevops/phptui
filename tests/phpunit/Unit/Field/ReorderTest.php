<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionType;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Reorder;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the reorder field.
 */
#[CoversClass(Reorder::class)]
#[CoversClass(AbstractField::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[Group('field')]
final class ReorderTest extends TestCase {

  use AssertsPagingTrait;

  public function testShowsHighlightedItemDescription(): void {
    $field = new Reorder([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('carrot', 'Carrot', 'Stays crisp when kept cold.'),
    ]);

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($field->view(new DefaultTheme())));

    $field->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Stays crisp when kept cold.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testNonSelectableItemShowsNoDescription(): void {
    // The cursor starts on the non-selectable heading, so its description
    // never renders beneath the list.
    $field = new Reorder([
      new Option('', 'Group', 'group note', OptionType::Heading),
      new Option('a', 'Apple', 'Crisp and sweet.'),
    ]);

    $this->assertStringNotContainsString('group note', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testEmptyReorderRendersNoDescription(): void {
    // A reorder with no items must render without touching a highlighted row.
    $field = new Reorder([]);

    $this->assertSame('', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testGrabAndMoveDownAccepts(): void {
    $field = new Reorder(self::options());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['b', 'c', 'a'], $value);
  }

  public function testNavigateThenGrabMoveUp(): void {
    $field = new Reorder(self::options());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'c', 'b'], $value);
  }

  public function testDefaultOrder(): void {
    $field = new Reorder(self::options(), ['c', 'a']);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['c', 'a', 'b'], $value);
  }

  public function testDefaultCompletesAndCleans(): void {
    // A partial default with an unknown ("x") and a duplicate ("b") still
    // resolves to a full ranking: known values first, remainder appended.
    $field = new Reorder(self::options(), ['b', 'x', 'b']);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['b', 'a', 'c'], $value);
  }

  public function testCancelReturnsNull(): void {
    $field = new Reorder(self::options());

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertNull($value);
    $this->assertTrue($field->isCancelled());
  }

  public function testGrabbedClampsAtTop(): void {
    $field = new Reorder(self::options());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testGrabbedClampsAtBottom(): void {
    $field = new Reorder(self::options());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testNavigationClampsAtTop(): void {
    $field = new Reorder(self::options());

    // Up at the top is a no-op; grabbing then moving down still works.
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['b', 'a', 'c'], $value);
  }

  public function testNavigationClampsAtBottom(): void {
    $field = new Reorder(self::options());

    // A third Down stays on the last row; grabbing then moving up still works.
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'c', 'b'], $value);
  }

  public function testGrabTogglesOffThenNavigates(): void {
    $field = new Reorder(self::options());

    // Grab then drop: the following Down navigates rather than moving the item.
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'c'], $value);
  }

  public function testLiveValueReflectsMovesBeforeAccept(): void {
    $field = new Reorder(self::options());

    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Down));

    $this->assertSame(['b', 'a', 'c'], $field->value());
  }

  public function testViewMarkersDegradeWithUnicodeMode(): void {
    $field = new Reorder(self::options());

    $before = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('❯', $before);
    $this->assertStringNotContainsString('↑↓', $before);

    $field->handle(Key::named(KeyName::Space));

    $grabbed = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('↑↓', $grabbed);

    $ascii = Ansi::strip($field->view(new DefaultTheme(76, ['unicode' => FALSE])));
    $this->assertStringContainsString('^v', $ascii);
  }

  public function testHints(): void {
    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], (new Reorder(self::options()))->hints());

    $this->assertSame([
      ['move', [Action::MoveUp, Action::MoveDown]],
      ['grab', [Action::Grab]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testHintsWhileHoldingItem(): void {
    $field = new Reorder(self::options());
    $field->handle(Key::named(KeyName::Space));

    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], $field->hints());

    // Holding an item swaps to reorder/drop labels and drops the accept hint -
    // the form cannot be accepted while an item is held.
    $this->assertSame([
      ['reorder', [Action::MoveUp, Action::MoveDown]],
      ['drop', [Action::Grab]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testEnterDropsHeldItemInsteadOfAccepting(): void {
    $field = new Reorder(self::options());

    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Enter));

    // Enter dropped the held item rather than accepting the form.
    $this->assertFalse($field->isComplete());
    $this->assertSame(['b', 'a', 'c'], $field->value());

    // A second Enter, with nothing held, accepts.
    $field->handle(Key::named(KeyName::Enter));
    $this->assertTrue($field->isComplete());
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): Reorder => new Reorder(self::options(), page_size: $size), -3);
  }

  public function testPagesLongList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): Reorder => new Reorder(self::pagingOptions(), page_size: $size));
  }

  /**
   * The three-item fixture used across most cases.
   *
   * @return array<string,string>
   *   The value => label option map.
   */
  protected static function options(): array {
    return ['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'];
  }

}
