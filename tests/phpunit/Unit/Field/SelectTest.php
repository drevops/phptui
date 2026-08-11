<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionType;
use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Capability\FilterCapableTrait;
use DrevOps\Tui\Field\Capability\OptionsCapableTrait;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Field\Capability\SelectionCapableTrait;
use DrevOps\Tui\Field\Select;
use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\FieldRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the select field, single-choice and multiple-choice.
 */
#[CoversClass(Select::class)]
#[CoversClass(AbstractField::class)]
#[CoversTrait(OptionsCapableTrait::class)]
#[CoversTrait(SelectionCapableTrait::class)]
#[CoversTrait(SelectionBoundedTrait::class)]
#[CoversTrait(FilterCapableTrait::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[Group('field')]
final class SelectTest extends TestCase {

  use AssertsPagingTrait;
  use MixedOptionsTrait;

  public function testNavigatesAndSelects(): void {
    $field = new Select(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'], 'a');

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
    $this->assertStringContainsString('●', $field->view(new DefaultTheme()));
  }

  public function testDefaultHighlight(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], 'b');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('b', $value);
  }

  public function testBoundsClamp(): void {
    $field = new Select(['a' => 'A', 'b' => 'B']);

    $field->handle(Key::named(KeyName::Up));
    $this->assertSame('a', $field->value());

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame('b', $field->value());
  }

  public function testRefusalIsShownUnderTheList(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], 'a');

    $field->refused('Not allowed.');

    $this->assertStringContainsString('Not allowed.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testCancel(): void {
    $field = new Select(['a' => 'A', 'b' => 'B']);

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testSetKeysInjectsBindings(): void {
    // An injected scope map takes over from the lazy default: the vim select
    // scope binds j to move-down, which the default preset does not.
    $field = (new Select(['a' => 'A', 'b' => 'B'], 'a'))
      ->setKeys(KeyMapManager::create('vim')->forField(FieldType::Select));

    $field->handle(Key::char('j'));

    $this->assertSame('b', $field->value());
  }

  public function testNavigationSkipsHeadingsSeparatorsAndDisabled(): void {
    $field = new Select($this->mixedOptions());

    // From Apple (0): Down skips the heading to Banana (2); Down skips the
    // separator and the disabled Cherry to Date (5).
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverDisabled(): void {
    $field = new Select($this->mixedOptions());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $field = new Select($this->mixedOptions(), 'c');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testRendersHeadingSeparatorAndDisabledReason(): void {
    $view = Ansi::strip((new Select($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testShowsHighlightedOptionDescription(): void {
    $field = new Select([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ], 'apple');

    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Crisp and sweet.', $view);
    $this->assertStringNotContainsString('Rich in potassium.', $view);

    $field->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Rich in potassium.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testOmitsDescriptionWhenHighlightedOptionHasNone(): void {
    $field = new Select([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana'),
    ], 'banana');

    // The highlighted Banana has no description, and Apple's never leaks in.
    $this->assertSame("○ Apple\n● Banana", Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testMultipleShowsCursorOptionDescription(): void {
    $field = new Select([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ], [], TRUE);

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testWrapsDescriptionToContentWidth(): void {
    $field = new Select([
      new Option('apple', 'Apple', 'Crisp and sweet and best eaten fresh from the tree.'),
    ], 'apple');

    $theme = new DefaultTheme(24);
    $lines = explode("\n", Ansi::strip($field->view($theme)));

    // The option row plus at least two wrapped description lines, each fitting.
    $this->assertGreaterThan(2, count($lines));
    foreach (array_slice($lines, 1) as $line) {
      $this->assertLessThanOrEqual($theme->contentWidth(), mb_strlen($line));
    }
  }

  public function testOmitsDescriptionWhenPanelTooNarrow(): void {
    $field = new Select([new Option('apple', 'Apple', 'Crisp and sweet.')], 'apple');

    $this->assertStringNotContainsString('Crisp', Ansi::strip($field->view(new DefaultTheme(6))));
  }

  public function testNonSelectableRowDescriptionNeverShows(): void {
    // With no selectable option the cursor parks on the heading; its
    // description must not render as an option description.
    $field = new Select([new Option('', 'Fruit', 'group note', OptionType::Heading)]);

    $this->assertStringNotContainsString('group note', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testNoSelectableRowYieldsNoValue(): void {
    $field = new Select([new Option('', 'Group', '', OptionType::Heading)]);

    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
    $this->assertSame('', $field->value());
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): Select => new Select(['a' => 'A'], page_size: $size), 0);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): Select => new Select(self::pagingOptions(), page_size: $size));
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Select(['a' => 'A']))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

  public function testMultipleToggleAndAccept(): void {
    $field = new Select(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'], [], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b'], $value);
  }

  public function testMultipleDefaultSelected(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], ['b'], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['b'], $value);
  }

  public function testMultipleFilterNarrowsThenToggles(): void {
    $field = new Select(['apple' => 'Apple', 'apricot' => 'Apricot', 'banana' => 'Banana'], [], TRUE);

    $field->handle(Key::char('b'));
    $field->handle(Key::char('a'));
    $field->handle(Key::char('n'));
    $this->assertStringContainsString('Banana', $field->view(new DefaultTheme()));
    $this->assertStringNotContainsString('Apple', $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Space));
    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testMultipleFilterBackspaceRestoresList(): void {
    $field = new Select(['apple' => 'Apple', 'banana' => 'Banana'], [], TRUE);

    $field->handle(Key::char('b'));
    $this->assertStringNotContainsString('Apple', $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Backspace));
    $this->assertStringContainsString('Apple', $field->view(new DefaultTheme()));
  }

  public function testMultipleSelectAllAndNone(): void {
    $field = new Select(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE);

    $field->handle(Key::named(KeyName::Right));
    $this->assertSame(['a', 'b', 'c'], $field->value());

    $field->handle(Key::named(KeyName::Left));
    $this->assertSame([], $field->value());
  }

  public function testMultipleCancel(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], [], TRUE);

    FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
  }

  public function testMultipleUpMovesCursorBack(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], [], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a'], $value);
  }

  public function testMultipleToggleOffDeselects(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], ['b'], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleToggleWithNoMatchesIsNoop(): void {
    $field = new Select(['a' => 'Apple'], [], TRUE);

    $field->handle(Key::char('z'));
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleHints(): void {
    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], (new Select(['a' => 'A'], [], TRUE))->hints());

    $this->assertSame([
      ['select', [Action::Toggle]],
      ['move', [Action::MoveUp, Action::MoveDown]],
      ['select none or all', [Action::SelectNone, Action::SelectAll]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testMultipleSpaceSkipsDisabledAndTogglesSelectable(): void {
    $field = new Select($this->mixedOptions(), [], TRUE);

    // Toggle Apple, skip the heading to Banana and toggle it, skip the
    // separator and the disabled Cherry to Date and toggle it.
    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b', 'd'], $value);
  }

  public function testMultipleSelectAllSkipsDisabled(): void {
    $field = new Select($this->mixedOptions(), [], TRUE);

    $field->handle(Key::named(KeyName::Right));

    $this->assertSame(['a', 'b', 'd'], $field->value());
  }

  public function testMultipleDefaultExcludesDisabled(): void {
    $field = new Select($this->mixedOptions(), ['c', 'a'], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['a'], $value);
  }

  public function testMultipleFilterDropsHeadingsAndSeparators(): void {
    $field = new Select($this->mixedOptions(), [], TRUE);

    $field->handle(Key::char('b'));
    $field->handle(Key::char('a'));
    $field->handle(Key::char('n'));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testMultipleDisabledMatchingFilterIsShownButNotToggleable(): void {
    $field = new Select($this->mixedOptions(), [], TRUE);

    $field->handle(Key::char('e'));
    $field->handle(Key::char('r'));
    $field->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($field->view(new DefaultTheme())));

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new Select($this->mixedOptions(), [], TRUE))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testMultipleFilterStaysSubstringNotFuzzy(): void {
    $field = new Select(['banana' => 'Banana', 'apple' => 'Apple'], [], TRUE);

    // "bn" is a subsequence of "Banana" but not a substring, so the checkbox
    // list - which stays substring-only - narrows it away.
    $field->handle(Key::char('b'));
    $field->handle(Key::char('n'));

    $this->assertStringNotContainsString('Banana', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testMultipleRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): Select => new Select(['a' => 'A'], [], TRUE, page_size: $size), -3);
  }

  public function testMultiplePagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): Select => new Select(self::pagingOptions(), [], TRUE, page_size: $size));
  }

  public function testMultipleOffersTheCountOutsideItsBoundsRatherThanRefusingIt(): void {
    $field = new Select(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE, selection_bounds: new SelectionBounds(2));

    // One selection is below the minimum of two, and is still offered: how many
    // are enough is measured where the answer is held.
    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertTrue($field->isComplete());
    $this->assertSame(['a'], $field->value());
    $this->assertNull($field->error());
  }

  public function testMultipleStatesItsBoundsUntilOneIsMissed(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], [], TRUE, selection_bounds: new SelectionBounds(NULL, 1));

    $this->assertStringContainsString('Select at most 1 item.', Ansi::strip($field->view(new DefaultTheme())));

    // The reason gives way to nothing else: it lands where the standing
    // statement of the same limit was, so the two never stack.
    $field->refused('Select at most 1 item.');
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertSame(1, substr_count($view, 'Select at most 1 item.'));
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $field = new Select(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a'], $value);
    $this->assertTrue($field->isComplete());
  }

  public function testMultipleSelectionHintShownInView(): void {
    $field = new Select(['a' => 'A', 'b' => 'B'], [], TRUE, selection_bounds: new SelectionBounds(1, 2));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    // The active limit is surfaced, capitalized, below the option list.
    $this->assertStringContainsString('Select between 1 and 2 items.', $view);
    $this->assertGreaterThan(strpos($view, 'B'), strpos($view, 'Select between 1 and 2 items.'));
  }

  public function testHighlightedDetailSitsAboveTheConstraint(): void {
    $field = new Select([
      new Option('a', 'A', 'Crisp and sweet, the everyday choice.'),
      new Option('b', 'B'),
    ], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $view = Ansi::strip($field->view(new DefaultTheme()));

    // The detail changes as the highlight moves, so it belongs against the list
    // it follows rather than below a limit that never moves.
    $this->assertLessThan(strpos($view, 'Select between 1 and 2 items.'), strpos($view, 'Crisp and sweet, the everyday choice.'));
  }

  public function testMultipleSelectionHintReadsApartFromAnOptionDescription(): void {
    $theme = new DefaultTheme();
    $field = new Select([
      new Option('a', 'A', 'Crisp and sweet, the everyday choice.'),
      new Option('b', 'B'),
    ], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $view = $field->view($theme);

    // The two lines sit next to each other, so drawn in one style the limit
    // the field is stating cannot be told from prose about the highlighted
    // option.
    $this->assertStringContainsString($this->styleOf($theme->fieldConstraint(...)) . 'Select between 1 and 2 items.', $view);
    $this->assertStringContainsString($this->styleOf($theme->fieldEntryDescription(...)) . 'Crisp and sweet, the everyday choice.', $view);
    $this->assertNotSame($this->styleOf($theme->fieldConstraint(...)), $this->styleOf($theme->fieldEntryDescription(...)));
  }

  /**
   * The escape sequence a theme style opens with.
   *
   * @param callable(string): string $style
   *   The style to sample.
   *
   * @return string
   *   The sequence preceding the styled text.
   */
  protected function styleOf(callable $style): string {
    $sample = $style('@');

    return substr($sample, 0, (int) strpos($sample, '@'));
  }

}
