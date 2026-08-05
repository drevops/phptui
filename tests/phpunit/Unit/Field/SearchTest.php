<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\SelectionBounds;
use DrevOps\Tui\Field\Capability\FilterCapableTrait;
use DrevOps\Tui\Field\Capability\OptionsCapableTrait;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Capability\SearchCapableTrait;
use DrevOps\Tui\Field\Capability\SelectionBoundedTrait;
use DrevOps\Tui\Field\Capability\SelectionCapableTrait;
use DrevOps\Tui\Field\Search;
use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
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
 * Tests the search field, single-choice and multiple-choice.
 */
#[CoversClass(Search::class)]
#[CoversTrait(SelectionCapableTrait::class)]
#[CoversTrait(SelectionBoundedTrait::class)]
#[CoversTrait(FilterCapableTrait::class)]
#[CoversTrait(SearchCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[CoversTrait(OptionsCapableTrait::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[Group('field')]
final class SearchTest extends TestCase {

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
    $field = new Search([
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

  public function testNoDescriptionWhenFilterMatchesNothing(): void {
    $field = new Search([new Option('apple', 'Apple', 'Crisp and sweet.')]);

    // A query that matches nothing leaves no highlighted option, so no
    // description line is appended.
    $field->handle(Key::char('z'));

    $this->assertStringNotContainsString('Crisp', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testDescriptionFollowsFilteredHighlight(): void {
    $field = new Search([
      new Option('apple', 'Apple', 'Crisp and sweet.'),
      new Option('banana', 'Banana', 'Rich in potassium.'),
    ]);

    $field->handle(Key::char('b'));
    $field->handle(Key::char('a'));
    $field->handle(Key::char('n'));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('Rich in potassium.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testFilterNarrowsAndEnterAcceptsValue(): void {
    $field = new Search($this->labels);

    $value = FieldRunner::run($field, ArrayKeyStream::of('circle', Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testDefaultSeedsHighlight(): void {
    $field = new Search($this->labels, 'none');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('none', $value);
  }

  public function testArrowsMoveHighlight(): void {
    $field = new Search($this->labels);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('circleci', $value);
  }

  public function testEnterIgnoredWhenNothingMatches(): void {
    $field = new Search($this->labels);

    $field->handle(Key::char('z'));
    $field->handle(Key::char('z'));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());

    $field->handle(Key::named(KeyName::Backspace));
    $field->handle(Key::named(KeyName::Backspace));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertTrue($field->isComplete());
    $this->assertSame('gha', $field->value());
  }

  public function testBackspaceRemovesWholeMultibyteCharacter(): void {
    $field = new Search($this->labels);

    // One backspace removes the whole multibyte character, not one byte, so
    // the cleared filter shows every option again instead of matching nothing.
    $field->handle(Key::char('é'));
    $field->handle(Key::named(KeyName::Backspace));
    $field->handle(Key::named(KeyName::Enter));

    $this->assertTrue($field->isComplete());
    $this->assertSame('gha', $field->value());
  }

  public function testSpaceIsPartOfTheQuery(): void {
    $field = new Search($this->labels);

    $value = FieldRunner::run($field, ArrayKeyStream::of('hub', Key::named(KeyName::Space), Key::named(KeyName::Backspace), Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testViewShowsQueryAndVisibleOptions(): void {
    $field = new Search($this->labels);

    $field->handle(Key::char('c'));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('c█', $view);
    $this->assertStringContainsString('CircleCI', $view);
    $this->assertStringNotContainsString('None', $view);
    $this->assertSame('c', $field->filter());
  }

  public function testCancel(): void {
    $field = new Search($this->labels);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Escape)));

    $this->assertTrue($field->isCancelled());
    $this->assertNull($value);
  }

  public function testNavigationSkipsNonSelectable(): void {
    $field = new Search($this->mixedOptions());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('d', $value);
  }

  public function testUpSkipsBackOverNonSelectable(): void {
    $field = new Search($this->mixedOptions());

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Down),
      Key::named(KeyName::Down),
      Key::named(KeyName::Up),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame('b', $value);
  }

  public function testDefaultOnDisabledFallsBackToFirstSelectable(): void {
    $field = new Search($this->mixedOptions(), 'c');

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame('a', $value);
  }

  public function testFilterDropsHeadingsAndSeparators(): void {
    $field = new Search($this->mixedOptions());

    $field->handle(Key::char('b'));
    $field->handle(Key::char('a'));
    $field->handle(Key::char('n'));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString('Banana', $view);
    $this->assertStringNotContainsString('Fruits', $view);
    $this->assertStringNotContainsString('Apple', $view);
    $this->assertStringNotContainsString('──', $view);
  }

  public function testDisabledMatchingFilterNotAccepted(): void {
    $field = new Search($this->mixedOptions());

    $field->handle(Key::char('e'));
    $field->handle(Key::char('r'));
    $field->handle(Key::char('r'));
    $this->assertStringContainsString('Cherry (out of stock)', Ansi::strip($field->view(new DefaultTheme())));

    $field->handle(Key::named(KeyName::Enter));

    $this->assertFalse($field->isComplete());
  }

  public function testRendersHeadingSeparatorAndDisabled(): void {
    $view = Ansi::strip((new Search($this->mixedOptions()))->view(new DefaultTheme()));

    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $field = new Search(['gha' => 'GitHub Actions', 'gitlab' => 'GitLab CI', 'circle' => 'CircleCI']);

    $value = FieldRunner::run($field, ArrayKeyStream::of('gha', Key::named(KeyName::Enter)));

    $this->assertSame('gha', $value);
  }

  public function testRanksPrefixAheadOfLooserSubsequence(): void {
    $field = new Search(['alpha' => 'Alpha', 'beta' => 'Beta', 'palace' => 'Palace']);

    // "pa" prefixes Palace but only scatters through Alpha, so Palace ranks
    // first and the cursor lands on it even though Alpha is declared earlier.
    $value = FieldRunner::run($field, ArrayKeyStream::of('pa', Key::named(KeyName::Enter)));

    $this->assertSame('palace', $value);
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $field = new Search(['palace' => 'Palace', 'alpha' => 'Alpha']);

    $field->handle(Key::char('p'));
    $field->handle(Key::char('a'));
    $view = $field->view($theme);

    $this->assertStringContainsString($theme->fieldEntryMatch('Pa'), $view);
    $this->assertStringContainsString('Palace', Ansi::strip($view));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): Search => new Search(['a' => 'A'], page_size: $size), -2);
  }

  public function testPagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): Search => new Search(self::pagingOptions(), page_size: $size));
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Search($this->labels))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

  public function testMultipleFilterToggleAndAccept(): void {
    $field = new Search($this->services, [], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of('sol', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['solr'], $value);
  }

  public function testMultipleSeededSelectionKept(): void {
    $field = new Search($this->services, ['redis'], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Enter)));

    $this->assertSame(['redis'], $value);
  }

  public function testMultipleViewShowsQueryLineAboveOptions(): void {
    $field = new Search($this->services, [], TRUE);

    $field->handle(Key::char('r'));
    $view = Ansi::strip($field->view(new DefaultTheme()));

    $this->assertStringContainsString("r█\n", $view);
    $this->assertStringContainsString('Redis', $view);
    $this->assertStringNotContainsString('ClamAV', $view);
  }

  public function testMultipleHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Search($this->services, [], TRUE))->hints());

    $this->assertSame(['select', 'move', 'select none or all', 'accept', 'cancel'], $labels);
  }

  public function testMultipleSkipsNonSelectableWhenToggling(): void {
    $field = new Search($this->mixedOptions(), [], TRUE);

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

  public function testMultipleRendersKindsBelowQueryLine(): void {
    $view = Ansi::strip((new Search($this->mixedOptions(), [], TRUE))->view(new DefaultTheme()));

    $this->assertStringContainsString("█\n", $view);
    $this->assertStringContainsString('Fruits', $view);
    $this->assertStringContainsString('Cherry (out of stock)', $view);
    $this->assertStringContainsString('──', $view);
  }

  public function testMultipleFuzzyMatchesNonContiguousSubsequence(): void {
    $field = new Search(['banana' => 'Banana', 'apple' => 'Apple', 'cherry' => 'Cherry'], [], TRUE);

    // "bn" is not a substring of any label but is a subsequence of "Banana".
    $value = FieldRunner::run($field, ArrayKeyStream::of('bn', Key::named(KeyName::Space), Key::named(KeyName::Enter)));

    $this->assertSame(['banana'], $value);
  }

  public function testMultipleHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $field = new Search(['banana' => 'Banana'], [], TRUE);

    $field->handle(Key::char('b'));
    $field->handle(Key::char('n'));
    $view = $field->view($theme);

    // The non-contiguous match highlights each hit character on its own,
    // leaving the intervening characters unstyled.
    $this->assertStringContainsString($theme->fieldEntryMatch('B'), $view);
    $this->assertStringContainsString($theme->fieldEntryMatch('n'), $view);
    $this->assertStringContainsString('Banana', Ansi::strip($view));
  }

  public function testMultiplePagesLongOptionList(): void {
    $this->assertPagesAndFollowsCursor(static fn(int $size): Search => new Search(self::pagingOptions(), [], TRUE, page_size: $size));
  }

  public function testMultipleOffersTheCountOutsideItsBoundsRatherThanRefusingIt(): void {
    $field = new Search($this->services, [], TRUE, selection_bounds: new SelectionBounds(2));

    $field->handle(Key::named(KeyName::Space));
    $field->handle(Key::named(KeyName::Enter));

    // How many are enough is measured where the answer is held, so one is
    // offered rather than refused here.
    $this->assertTrue($field->isComplete());
    $this->assertNull($field->error());
  }

  public function testMultipleAcceptsWithinBounds(): void {
    $field = new Search($this->services, [], TRUE, selection_bounds: new SelectionBounds(1, 2));

    $value = FieldRunner::run($field, ArrayKeyStream::of(
      Key::named(KeyName::Space),
      Key::named(KeyName::Enter),
    ));

    $this->assertSame(['clamav'], $value);
    $this->assertTrue($field->isComplete());
  }

  public function testMultipleSelectionHintShownBelowQueryLine(): void {
    $field = new Search($this->services, [], TRUE, selection_bounds: new SelectionBounds(2, 3));

    // The active limit is surfaced before it is reached.
    $this->assertStringContainsString('Select between 2 and 3 items.', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testPlaceholderGhostsAnEmptyQueryOnly(): void {
    $field = (new Search($this->services))->setPlaceholder('Type to filter');
    $theme = new DefaultTheme();

    $this->assertStringContainsString('Type to filter', Ansi::strip($field->view($theme)));

    $field->handle(Key::char('c'));

    $this->assertStringNotContainsString('Type to filter', Ansi::strip($field->view($theme)));
  }

}
