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
use DrevOps\Tui\Widget\Capability\CompletionCapableTrait;
use DrevOps\Tui\Widget\Capability\PagingCapableTrait;
use DrevOps\Tui\Widget\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Widget\SuggestWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the suggest (autocomplete) widget.
 */
#[CoversClass(SuggestWidget::class)]
#[CoversClass(AbstractWidget::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[CoversTrait(CompletionCapableTrait::class)]
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

  public function testPlaceholderGhostsAnEmptyQueryOnly(): void {
    $widget = (new SuggestWidget(['Pear', 'Plum']))->setPlaceholder('Type to filter');
    $theme = new DefaultTheme();

    $this->assertStringContainsString('Type to filter', Ansi::strip($widget->view($theme)));

    $widget->handle(Key::char('P'));

    $this->assertStringNotContainsString('Type to filter', Ansi::strip($widget->view($theme)));
  }

  public function testGhostTextIsOptIn(): void {
    $widget = new SuggestWidget(['Apple', 'Apricot']);

    $widget->handle(Key::char('a'));

    // Without the opt-in the query line carries no dimmed suffix, and the keys
    // that would accept one are inert.
    $view = $widget->view(new DefaultTheme());
    $this->assertStringNotContainsString("\033[90m", $view);

    $widget->handle(Key::named(KeyName::Tab));
    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame('a', $widget->value());
  }

  public function testGhostTextRendersDimmedSuffix(): void {
    $widget = new SuggestWidget(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::char('p'));

    // The leading candidate's remainder is previewed dimmed (SGR 90) after the
    // caret, while the value stays the typed query until it is accepted.
    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('ple', $view);
    $this->assertStringContainsString("\033[90m", $view);
    $this->assertSame('ap', $widget->value());
  }

  public function testTabAcceptsGhostText(): void {
    $widget = new SuggestWidget(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('ap', Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    // Accepting adopts the candidate's own casing.
    $this->assertSame('Apple', $value);
  }

  public function testRightAcceptsGhostText(): void {
    $widget = new SuggestWidget(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $value = WidgetRunner::run($widget, ArrayKeyStream::of('ap', Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame('Apple', $value);
  }

  public function testAcceptingGhostTextKeepsTheListAvailable(): void {
    $widget = new SuggestWidget(['Apple', 'Apple pie', 'Apricot'], '', NULL, [], TRUE);

    $widget->handle(Key::char('a'));
    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame('Apple', $widget->value());

    // The completion re-queries rather than selecting: the narrowed list is
    // still open and still arrows into.
    $view = Ansi::strip($widget->view(new DefaultTheme()));
    $this->assertStringContainsString('Apple pie', $view);
    $this->assertStringNotContainsString('Apricot', $view);

    $widget->handle(Key::named(KeyName::Down));
    $widget->handle(Key::named(KeyName::Down));
    $this->assertSame('Apple pie', $widget->value());
  }

  public function testGhostTextSuppressedWhileSuggestionHighlighted(): void {
    $widget = new SuggestWidget(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $widget->handle(Key::char('a'));
    $this->assertStringContainsString("\033[90m", $widget->view(new DefaultTheme()));

    // Arrowing into the list makes the highlighted row the live value, so a
    // preview of the typed query would contradict it.
    $widget->handle(Key::named(KeyName::Down));
    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));

    // Tab and Right stay inert while a row is highlighted.
    $widget->handle(Key::named(KeyName::Tab));
    $widget->handle(Key::named(KeyName::Right));
    $this->assertSame('Apple', $widget->value());
  }

  public function testGhostTextSuppressedWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);
    $widget = new SuggestWidget(['Apricot'], '', NULL, [], TRUE);

    $widget->handle(Key::char('a'));

    // Without colour the preview cannot be dimmed, so it is dropped rather than
    // rendered as plain text indistinguishable from the typed query. The
    // suggestion itself still lists below, and no escapes leak into the line.
    $this->assertSame('a' . $theme->caret(), $widget->queryLine($theme));
    $this->assertStringNotContainsString("\033", $widget->view($theme));
  }

  public function testGhostTextCompletesPrefixesNotFuzzyMatches(): void {
    $widget = new SuggestWidget(['Green apple'], '', NULL, [], TRUE);

    $widget->handle(Key::char('g'));
    $widget->handle(Key::char('a'));

    // "ga" is a scattered subsequence, so the row is listed but there is no
    // suffix to draw after the caret.
    $view = $widget->view(new DefaultTheme());
    $this->assertStringContainsString('Green apple', Ansi::strip($view));
    $this->assertStringNotContainsString("\033[90m", $view);
  }

  public function testFullyTypedSuggestionHasNoGhostText(): void {
    $widget = new SuggestWidget(['Fig'], '', NULL, [], TRUE);

    $widget->handle(Key::char('f'));
    $widget->handle(Key::char('i'));
    $widget->handle(Key::char('g'));

    // The query already equals the only candidate; nothing is left to preview.
    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));
  }

  public function testEmptyQueryShowsNoGhostText(): void {
    // With nothing typed there is no prefix to complete.
    $widget = new SuggestWidget(['Apple'], '', NULL, [], TRUE);

    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));
  }

  public function testGhostTextIsUnicodeAware(): void {
    // Folding is per code point, so a non-ASCII prefix matches and the suffix
    // renders whole rather than splitting mid-character.
    $widget = new SuggestWidget(['Éclair'], '', NULL, [], TRUE);

    $widget->handle(Key::char('é'));
    $this->assertStringContainsString('clair', $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame('Éclair', $widget->value());
  }

  public function testGhostTextBacksOffWhenTheQueryStopsMatching(): void {
    $widget = new SuggestWidget(['Apple'], '', NULL, [], TRUE);

    $widget->handle(Key::char('a'));
    $this->assertStringContainsString("\033[90m", $widget->view(new DefaultTheme()));

    // A typo drops every prefix candidate, so the preview disappears and Tab
    // leaves the query untouched.
    $widget->handle(Key::char('z'));
    $this->assertStringNotContainsString("\033[90m", $widget->view(new DefaultTheme()));

    $widget->handle(Key::named(KeyName::Tab));
    $this->assertSame('az', $widget->value());
  }

}
