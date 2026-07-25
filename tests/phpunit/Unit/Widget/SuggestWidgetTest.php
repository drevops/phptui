<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Widget;

use DrevOps\Tui\Input\Hint;
use DrevOps\Tui\Input\Key;
use DrevOps\Tui\Input\KeyName;
use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Testing\ArrayKeyStream;
use DrevOps\Tui\Testing\WidgetRunner;
use DrevOps\Tui\Tests\Traits\AssertsPagingTrait;
use DrevOps\Tui\Theme\DefaultTheme;
use DrevOps\Tui\Widget\AbstractWidget;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\SuggestWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the suggest (autocomplete) widget.
 */
#[CoversClass(SuggestWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversClass(PagingCapableTrait::class)]
#[Group('widget')]
final class SuggestWidgetTest extends TestCase {

  use AssertsPagingTrait;

  public function testTypeAcceptsBuffer(): void {
    $widget = new SuggestWidget(['UTC', 'Europe/London', 'Australia/Sydney']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('UTC', Key::named(KeyName::Enter)));

    $this->assertSame('UTC', $value);
  }

  public function testNarrowsAndSelectsSuggestion(): void {
    $widget = new SuggestWidget(['UTC', 'Europe/London', 'Australia/Sydney']);

    $widget->handle(Key::char('l'));
    $widget->handle(Key::char('o'));
    $widget->handle(Key::char('n'));
    $this->assertStringContainsString('Europe/London', Ansi::strip($widget->view(new DefaultTheme())));
    $this->assertStringNotContainsString('Australia/Sydney', Ansi::strip($widget->view(new DefaultTheme())));

    $value = WidgetRunner::run($widget, ArrayKeyStream::of(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('Europe/London', $value);
  }

  public function testEmptyBufferListsAll(): void {
    $widget = new SuggestWidget(['x', 'y']);

    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame('x', $widget->value());
    $this->assertStringContainsString('y', $widget->view(new DefaultTheme()));
  }

  public function testBackspaceAndUpResetHighlight(): void {
    $widget = new SuggestWidget(['abc', 'abd']);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame('abc', $widget->value());

    $widget->handle(Key::named(KeyName::Up));
    $this->assertSame('a', $widget->value());

    $widget->handle(Key::char('b'));
    $widget->handle(Key::named(KeyName::Backspace));
    $this->assertSame('a', $widget->value());
  }

  public function testBufferExposesTheLiveQuery(): void {
    $widget = new SuggestWidget(['alpha']);

    $widget->handle(Key::char('a'));

    $this->assertSame('a', $widget->buffer());
  }

  public function testCancel(): void {
    $widget = new SuggestWidget(['x', 'y']);

    $widget->handle(Key::named(KeyName::Escape));

    $this->assertTrue($widget->isCancelled());
  }

  public function testSpaceAppendsToBuffer(): void {
    $widget = new SuggestWidget(['x', 'y']);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::named(KeyName::Space));

    $this->assertSame('a ', $widget->value());
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $widget = new SuggestWidget(['GitHub Actions', 'GitLab CI', 'CircleCI']);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('gha', Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('GitHub Actions', $value);
  }

  public function testRanksPrefixAheadOfLooserSubsequence(): void {
    $widget = new SuggestWidget(['Alpha', 'Beta', 'Palace']);

    // "pa" is a prefix of Palace but only a scattered subsequence of Alpha, so
    // Palace ranks first and the first Down lands on it.
    $widget->handle(Key::char('p'));
    $widget->handle(Key::char('a'));
    $widget->handle(Key::named(KeyName::Down));

    $this->assertSame('Palace', $widget->value());
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $widget = new SuggestWidget(['Alpha', 'Beta', 'Palace']);

    $widget->handle(Key::char('p'));
    $widget->handle(Key::char('a'));
    $view = $widget->view($theme);

    // The matched "Pa" prefix is themed as a match run; the label is intact
    // once the styling is stripped.
    $this->assertStringContainsString($theme->highlightMatch('Pa'), $view);
    $this->assertStringContainsString('Palace', Ansi::strip($view));
  }

  public function testShowsHighlightedSuggestionDescription(): void {
    $widget = new SuggestWidget(['apple', 'apricot'], '', NULL, ['apple' => 'Crisp and sweet.', 'apricot' => 'Small and tart.']);

    // With nothing highlighted yet (cursor detached), no description shows.
    $this->assertStringNotContainsString('Crisp and sweet.', Ansi::strip($widget->view(new DefaultTheme())));

    $widget->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('Crisp and sweet.', $view);
    $this->assertStringNotContainsString('Small and tart.', $view);

    $widget->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('Small and tart.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testOmitsDescriptionForSuggestionWithoutEntry(): void {
    $widget = new SuggestWidget(['apple', 'pear'], '', NULL, ['apple' => 'Crisp and sweet.']);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));

    // The highlighted Pear has no description entry, so nothing is appended.
    $this->assertStringNotContainsString('Crisp', Ansi::strip($widget->view(new DefaultTheme())));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): SuggestWidget => new SuggestWidget(['x'], page_size: $size), 0);
  }

  public function testPagesLongSuggestionList(): void {
    // The highlight starts detached (-1), so three Downs reach the third item.
    $this->assertPagesAndFollowsCursor(static fn(int $size): SuggestWidget => new SuggestWidget(array_values(self::pagingOptions()), page_size: $size), 3);
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new SuggestWidget(['UTC', 'GMT']))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

}
