<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Action;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyMapManager;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\OptionKind;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Widget\Capability\SelectionCapableTrait;
use DrevOps\Tui\Widget\SelectWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the select widget, single-choice and multiple-choice.
 */
#[CoversClass(SelectWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversTrait(OptionsCapableTrait::class)]
#[CoversTrait(SelectionCapableTrait::class)]
#[CoversTrait(SelectionBoundedTrait::class)]
#[CoversTrait(FilterCapableTrait::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[Group('widget')]
final class SelectWidgetTest extends TestCase {

  use AssertsPagingTrait;
  use MixedOptionsTrait;

  public function testNavigatesAndSelects(): void {
    $widget = new SelectWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'], 'a');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
    $this->assertStringContainsString('●', $widget->view(new DefaultTheme()));
  }

  public function testDefaultHighlight(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], 'b');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('b', $value);
  }

  public function testBoundsClamp(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B']);

    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame('a', $widget->value());

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame('b', $widget->value());
  }

  public function testValidatorErrorShownInView(): void {
    $widget = (new SelectWidget(['a' => 'A', 'b' => 'B'], 'a'))->setHandlers(validate: static fn (mixed $value): string => 'Not allowed.');

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Not allowed.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testCancel(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B']);

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testSetKeysInjectsBindings(): void {
    // An injected scope map takes over from the lazy default: the vim select
    // scope binds j to move-down, which the default preset does not.
    $widget = (new SelectWidget(['a' => 'A', 'b' => 'B'], 'a'))
      ->setKeys(KeyMapManager::create('vim')->forField(FieldType::Select));

    $widget->handle(Key::char('j'));

    $this->assertSame('b', $widget->value());
  }

  public function testNavigationSkipsHeadingsSeparatorsAndDisabled(): void {
    $widget = new SelectWidget($this->mixedOptions());

    // From Apple (0): Down skips the heading to Banana (2); Down skips the
    // separator and the disabled Cherry to Date (5).
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverDisabled(): void {
    $widget = new SelectWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $widget = new SelectWidget($this->mixedOptions(), 'c');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testRendersHeadingSeparatorAndDisabledReason(): void {
    $view = Ansi::strip((new SelectWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testShowsHighlightedOptionDescription(): void {
    $widget = new SelectWidget([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ], 'apple');

    $view = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('Crisp and sweet.', $view);
    $this->assertStringNotContainsString('Rich in potassium.', $view);

    $widget->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('Rich in potassium.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testOmitsDescriptionWhenHighlightedOptionHasNone(): void {
    $widget = new SelectWidget([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana'),
    ], 'banana');

    // The highlighted Banana has no description, and Apple's never leaks in.
    $this->assertSame("○ Apple\n● Banana", Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testMultipleShowsCursorOptionDescription(): void {
    $widget = new SelectWidget([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ], [], TRUE);

    $this->assertStringContainsString('Crisp and sweet.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testWrapsDescriptionToContentWidth(): void {
    $widget = new SelectWidget([
      new Option('apple', 'Apple', 'Crisp and sweet and best eaten fresh from the tree.'),
    ], 'apple');

    $theme = new DefaultTheme(24);
    $lines = explode("\n", Ansi::strip($widget->view($theme)));

    // The option row plus at least two wrapped description lines, each fitting.
    $this->assertGreaterThan(2, count($lines));
    foreach (array_slice($lines, 1) as $line) {
      $this->assertLessThanOrEqual($theme->contentWidth(), mb_strlen($line));
    }
  }

  public function testOmitsDescriptionWhenPanelTooNarrow(): void {
    $widget = new SelectWidget([new Option('apple', 'Apple', 'Crisp and sweet.')], 'apple');

    $this->assertStringNotContainsString('Crisp', Ansi::strip($widget->view(new DefaultTheme(6))));
  }

  public function testNonSelectableRowDescriptionNeverShows(): void {
    // With no selectable option the cursor parks on the heading; its
    // description must not render as an option description.
    $widget = new SelectWidget([new Option('', 'Fruit', 'group note', OptionKind::Heading)]);

    $this->assertStringNotContainsString('group note', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testNoSelectableRowYieldsNoValue(): void {
    $widget = new SelectWidget([new Option('', 'Group', '', OptionKind::Heading)]);

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertSame('', $widget->value());
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): SelectWidget => new SelectWidget(['a' => 'A'], page_size: $size), 0);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): SelectWidget => new SelectWidget(self::pagingOptions(), page_size: $size));
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new SelectWidget(['a' => 'A']))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

  public function testMultipleToggleAndAccept(): void {
    $widget = new SelectWidget(['a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry'], [], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a', 'b'], $value);
  }

  public function testMultipleDefaultSelected(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], ['b'], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['b'], $value);
  }

  public function testMultipleFilterNarrowsThenToggles(): void {
    $widget = new SelectWidget(['apple' => 'Apple', 'apricot' => 'Apricot', 'banana' => 'Banana'], [], TRUE);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $this->assertStringContainsString('Banana', $widget->view(new DefaultTheme()));
    $this->assertStringNotContainsString('Apple', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Space));
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testMultipleFilterBackspaceRestoresList(): void {
    $widget = new SelectWidget(['apple' => 'Apple', 'banana' => 'Banana'], [], TRUE);

    $widget->handle(Key::char('b'));
    $this->assertStringNotContainsString('Apple', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertStringContainsString('Apple', $widget->view(new DefaultTheme()));
  }

  public function testMultipleSelectAllAndNone(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE);

    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame(['a', 'b', 'c'], $widget->value());

    $widget->handle(Key::named(KeyName::Left));
    $this->assertSame([], $widget->value());
  }

  public function testMultipleCancel(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], [], TRUE);

    WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
  }

  public function testMultipleUpMovesCursorBack(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], [], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a'], $value);
  }

  public function testMultipleToggleOffDeselects(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], ['b'], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleToggleWithNoMatchesIsNoop(): void {
    $widget = new SelectWidget(['a' => 'Apple'], [], TRUE);

    $widget->handle(Key::char('z'));
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleHints(): void {
    $hints = array_map(static fn(Hint $hint): array => [$hint->label, $hint->actions], (new SelectWidget(['a' => 'A'], [], TRUE))->hints());

    $this->assertSame([
      ['select', [Action::Toggle]],
      ['move', [Action::MoveUp, Action::MoveDown]],
      ['select none or all', [Action::SelectNone, Action::SelectAll]],
      ['accept', [Action::Accept]],
      ['cancel', [Action::Cancel]],
    ], $hints);
  }

  public function testMultipleSpaceSkipsDisabledAndTogglesSelectable(): void {
    $widget = new SelectWidget($this->mixedOptions(), [], TRUE);

    // Toggle Apple, skip the heading to Banana and toggle it, skip the
    // separator and the disabled Cherry to Date and toggle it.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
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
    $widget = new SelectWidget($this->mixedOptions(), [], TRUE);

    $widget->handle(Key::named(KeyName::Right));

    $this->assertSame(['a', 'b', 'd'], $widget->value());
  }

  public function testMultipleDefaultExcludesDisabled(): void {
    $widget = new SelectWidget($this->mixedOptions(), ['c', 'a'], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['a'], $value);
  }

  public function testMultipleFilterDropsHeadingsAndSeparators(): void {
    $widget = new SelectWidget($this->mixedOptions(), [], TRUE);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testMultipleDisabledMatchingFilterIsShownButNotToggleable(): void {
    $widget = new SelectWidget($this->mixedOptions(), [], TRUE);

    $widget->handle(Key::char('e'));
    $widget->handle(Key::char('r'));
    $widget->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($widget->view(new DefaultTheme())));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame([], $value);
  }

  public function testMultipleRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new SelectWidget($this->mixedOptions(), [], TRUE))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testMultipleFilterStaysSubstringNotFuzzy(): void {
    $widget = new SelectWidget(['banana' => 'Banana', 'apple' => 'Apple'], [], TRUE);

    // "bn" is a subsequence of "Banana" but not a substring, so the checkbox
    // list - which stays substring-only - narrows it away.
    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('n'));

    $this->assertStringNotContainsString('Banana', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testMultipleRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): SelectWidget => new SelectWidget(['a' => 'A'], [], TRUE, page_size: $size), -3);
  }

  public function testMultiplePagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): SelectWidget => new SelectWidget(self::pagingOptions(), [], TRUE, page_size: $size));
  }

  public function testMultipleRejectsBelowMinWithInlineError(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE, selection_bounds: new SelectionBounds(2));

    // One selection is below the minimum of two, so the accept is rejected.
    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Select at least 2 items.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testMultipleRejectsAboveMaxWithInlineError(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], [], TRUE, selection_bounds: new SelectionBounds(NULL, 1));

    // Two selections exceed the maximum of one.
    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Select at most 1 item.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B', 'c' => 'C'], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['a'], $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testMultipleSelectionHintShownInView(): void {
    $widget = new SelectWidget(['a' => 'A', 'b' => 'B'], [], TRUE, selection_bounds: new SelectionBounds(1, 2));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    // The active limit is surfaced, capitalized, below the option list.
    $this->assertStringContainsString('Select between 1 and 2 items.', $view);
    $this->assertGreaterThan(strpos($view, 'B'), strpos($view, 'Select between 1 and 2 items.'));
  }

  public function testHighlightedDetailSitsAboveTheConstraint(): void {
    $widget = new SelectWidget([
      new Option('a', 'A', 'Crisp and sweet, the everyday choice.'),
      new Option('b', 'B'),
    ], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $view = Ansi::strip($widget->view(new DefaultTheme()));

    // The detail changes as the highlight moves, so it belongs against the list
    // it follows rather than below a limit that never moves.
    $this->assertLessThan(strpos($view, 'Select between 1 and 2 items.'), strpos($view, 'Crisp and sweet, the everyday choice.'));
  }

  public function testMultipleSelectionHintReadsApartFromAnOptionDescription(): void {
    $theme = new DefaultTheme();
    $widget = new SelectWidget([
      new Option('a', 'A', 'Crisp and sweet, the everyday choice.'),
      new Option('b', 'B'),
    ], [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $view = $widget->view($theme);

    // The two lines sit next to each other, so drawn in one style the limit
    // the field is stating cannot be told from prose about the highlighted
    // option.
    $this->assertStringContainsString($this->styleOf($theme->hint(...)) . 'Select between 1 and 2 items.', $view);
    $this->assertStringContainsString($this->styleOf($theme->entryDescription(...)) . 'Crisp and sweet, the everyday choice.', $view);
    $this->assertNotSame($this->styleOf($theme->hint(...)), $this->styleOf($theme->entryDescription(...)));
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
