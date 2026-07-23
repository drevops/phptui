<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Tests\Traits\MixedOptionsTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\Capability\FilterCapableTrait;
use DrevOps\Tui\Widget\Capability\OptionsCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\SearchCapableTrait;
use DrevOps\Tui\Widget\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Widget\Capability\SelectionCapableTrait;
use DrevOps\Tui\Widget\SearchWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the search widget, single-choice and multiple-choice.
 */
#[CoversClass(SearchWidget::class)]
#[CoversClass(SelectionCapableTrait::class)]
#[CoversClass(SelectionBoundedTrait::class)]
#[CoversClass(FilterCapableTrait::class)]
#[CoversClass(SearchCapableTrait::class)]
#[CoversClass(OptionsCapableTrait::class)]
#[CoversClass(PagingCapableTrait::class)]
#[Group('widget')]
final class SearchWidgetTest extends TestCase {

  use AssertsPagingTrait;
  use MixedOptionsTrait;

  /**
   * The options used across the single-choice tests.
   *
   * @var array<string,string>
   */
  protected array $labels = ['gha' => 'GitHub Actions', 'circleci' => 'CircleCI', 'none' => 'None'];

  /**
   * The options used across the multiple-choice tests.
   *
   * @var array<string,string>
   */
  protected array $services = ['clamav' => 'ClamAV', 'redis' => 'Redis', 'solr' => 'Solr'];

  public function testShowsHighlightedOptionDescription(): void {
    $widget = new SearchWidget([
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

  public function testNoDescriptionWhenFilterMatchesNothing(): void {
    $widget = new SearchWidget([new Option('apple', 'Apple', 'Crisp and sweet.')]);

    // A query that matches nothing leaves no highlighted option, so no
    // description line is appended.
    $widget->handle(Key::char('z'));

    $this->assertStringNotContainsString('Crisp', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testDescriptionFollowsFilteredHighlight(): void {
    $widget = new SearchWidget([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ]);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Rich in potassium.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testFilterNarrowsAndEnterAcceptsValue(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('circle', Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testDefaultSeedsHighlight(): void {
    $widget = new SearchWidget($this->labels, 'none');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('none', $value);
  }

  public function testArrowsMoveHighlight(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testEnterIgnoredWhenNothingMatches(): void {
    $widget = new SearchWidget($this->labels);

    $widget->handle(Key::char('z'));
    $widget->handle(Key::char('z'));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());

    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertSame('gha', $widget->value());
  }

  public function testBackspaceRemovesWholeMultibyteCharacter(): void {
    $widget = new SearchWidget($this->labels);

    // One backspace removes the whole multibyte character, not one byte, so
    // the cleared filter shows every option again instead of matching nothing.
    $widget->handle(Key::char('é'));
    $widget->handle(Key::named(KeyName::Backspace));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertTrue($widget->isComplete());
    $this->assertSame('gha', $widget->value());
  }

  public function testSpaceIsPartOfTheQuery(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('hub', Key::named(KeyName::Space), Key::named(KeyName::Backspace), Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testViewShowsQueryAndVisibleOptions(): void {
    $widget = new SearchWidget($this->labels);

    $widget->handle(Key::char('c'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('c█', $view);
    $this->assertStringContainsString('CircleCI', $view);
    $this->assertStringNotContainsString('None', $view);
    $this->assertSame('c', $widget->filter());
  }

  public function testCancel(): void {
    $widget = new SearchWidget($this->labels);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($widget->isCancelled());
    $this->assertNull($value);
  }

  public function testNavigationSkipsNonSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverNonSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $widget = new SearchWidget($this->mixedOptions(), 'c');

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testFilterDropsHeadingsAndSeparators(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('n'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testDisabledMatchingFilterNotAccepted(): void {
    $widget = new SearchWidget($this->mixedOptions());

    $widget->handle(Key::char('e'));
    $widget->handle(Key::char('r'));
    $widget->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($widget->view(new DefaultTheme())));

    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
  }

  public function testRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new SearchWidget($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $widget = new SearchWidget(['gha' => 'GitHub Actions', 'gitlab' => 'GitLab CI', 'circle' => 'CircleCI']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('gha', Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testRanksPrefixAheadOfLooserSubsequence(): void {
    $widget = new SearchWidget(['alpha' => 'Alpha', 'beta' => 'Beta', 'palace' => 'Palace']);

    // "pa" prefixes Palace but only scatters through Alpha, so Palace ranks
    // first and the cursor lands on it even though Alpha is declared earlier.
    $value = WidgetRunner::run($widget, ArrayKeyStream::of('pa', Key::named(KeyName::Enter)));

    $this->assertSame('palace', $value);
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $widget = new SearchWidget(['palace' => 'Palace', 'alpha' => 'Alpha']);

    $widget->handle(Key::char('p'));
    $widget->handle(Key::char('a'));
    $view = $widget->view($theme);

    $this->assertStringContainsString($theme->highlightMatch('Pa'), $view);
    $this->assertStringContainsString('Palace', Ansi::strip($view));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): SearchWidget => new SearchWidget(['a' => 'A'], page_size: $size), -2);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): SearchWidget => new SearchWidget(self::pagingOptions(), page_size: $size));
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new SearchWidget($this->labels))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

  public function testMultipleFilterToggleAndAccept(): void {
    $widget = new SearchWidget($this->services, [], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('sol', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['solr'], $value);
  }

  public function testMultipleSeededSelectionKept(): void {
    $widget = new SearchWidget($this->services, ['redis'], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['redis'], $value);
  }

  public function testMultipleViewShowsQueryLineAboveOptions(): void {
    $widget = new SearchWidget($this->services, [], TRUE);

    $widget->handle(Key::char('r'));
    $view = Ansi::strip($widget->view(new DefaultTheme()));

    $this->assertStringContainsString("r█\n", $view);
    $this->assertStringContainsString('Redis', $view);
    $this->assertStringNotContainsString('ClamAV', $view);
  }

  public function testMultipleHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new SearchWidget($this->services, [], TRUE))->hints());

    $this->assertSame(['select', 'move', 'none/all', 'accept', 'cancel'], $labels);
  }

  public function testMultipleSkipsNonSelectableWhenToggling(): void {
    $widget = new SearchWidget($this->mixedOptions(), [], TRUE);

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

  public function testMultipleRendersKindsBelowQueryLine(): void {
    $view = Ansi::strip((new SearchWidget($this->mixedOptions(), [], TRUE))->view(new DefaultTheme()));

    $this->assertStringContainsString("█\n", $view);
    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testMultipleFuzzyMatchesNonContiguousSubsequence(): void {
    $widget = new SearchWidget(['banana' => 'Banana', 'apple' => 'Apple', 'cherry' => 'Cherry'], [], TRUE);

    // "bn" is not a substring of any label but is a subsequence of "Banana".
    $value = WidgetRunner::run($widget, ArrayKeyStream::of('bn', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testMultipleHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $widget = new SearchWidget(['banana' => 'Banana'], [], TRUE);

    $widget->handle(Key::char('b'));
    $widget->handle(Key::char('n'));
    $view = $widget->view($theme);

    // The non-contiguous match highlights each hit character on its own,
    // leaving the intervening characters unstyled.
    $this->assertStringContainsString($theme->highlightMatch('B'), $view);
    $this->assertStringContainsString($theme->highlightMatch('n'), $view);
    $this->assertStringContainsString('Banana', Ansi::strip($view));
  }

  public function testMultiplePagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): SearchWidget => new SearchWidget(self::pagingOptions(), [], TRUE, page_size: $size));
  }

  public function testMultipleRejectsBelowMinWithInlineError(): void {
    $widget = new SearchWidget($this->services, [], TRUE, selection_bounds: new SelectionBounds(2, NULL));

    $widget->handle(Key::named(KeyName::Space));
    $widget->handle(Key::named(KeyName::Enter));

    $this->assertFalse($widget->isComplete());
    $this->assertStringContainsString('Select at least 2 items.', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $widget = new SearchWidget($this->services, [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['clamav'], $value);
    $this->assertTrue($widget->isComplete());
  }

  public function testMultipleSelectionHintShownBelowQueryLine(): void {
    $widget = new SearchWidget($this->services, [], TRUE, selection_bounds: new SelectionBounds(2, 3));

    // The active limit is surfaced before it is reached.
    $this->assertStringContainsString('between 2 and 3 items', Ansi::strip($widget->view(new DefaultTheme())));
  }

}
