<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Field\AbstractField;
use DrevOps\Tui\Field\Capability\CompletionCapableTrait;
use DrevOps\Tui\Field\Capability\PagingCapableTrait;
use DrevOps\Tui\Field\Capability\PlaceholderCapableTrait;
use DrevOps\Tui\Field\Suggest;
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
 * Tests the suggest (autocomplete) field.
 */
#[CoversClass(Suggest::class)]
#[CoversClass(AbstractField::class)]
#[CoversTrait(PagingCapableTrait::class)]
#[CoversTrait(PlaceholderCapableTrait::class)]
#[CoversTrait(CompletionCapableTrait::class)]
#[Group('field')]
final class SuggestTest extends TestCase {

  use AssertsPagingTrait;

  public function testTypeAcceptsBuffer(): void {
    $field = new Suggest(['UTC', 'Europe/London', 'Australia/Sydney']);

    $value = FieldRunner::run($field, ArrayKeyStream::of('UTC', Key::named(KeyName::Enter)));

    $this->assertSame('UTC', $value);
  }

  public function testNarrowsAndSelectsSuggestion(): void {
    $field = new Suggest(['UTC', 'Europe/London', 'Australia/Sydney']);

    $field->handle(Key::char('l'));
    $field->handle(Key::char('o'));
    $field->handle(Key::char('n'));
    $this->assertStringContainsString('Europe/London', Ansi::strip($field->view(new DefaultTheme())));
    $this->assertStringNotContainsString('Australia/Sydney', Ansi::strip($field->view(new DefaultTheme())));

    $value = FieldRunner::run($field, ArrayKeyStream::of(Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('Europe/London', $value);
  }

  public function testEmptyBufferListsAll(): void {
    $field = new Suggest(['x', 'y']);

    $field->handle(Key::named(KeyName::Down));
    $this->assertSame('x', $field->value());
    $this->assertStringContainsString('y', $field->view(new DefaultTheme()));
  }

  public function testBackspaceAndUpResetHighlight(): void {
    $field = new Suggest(['abc', 'abd']);

    $field->handle(Key::char('a'));
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame('abc', $field->value());

    $field->handle(Key::named(KeyName::Up));
    $this->assertSame('a', $field->value());

    $field->handle(Key::char('b'));
    $field->handle(Key::named(KeyName::Backspace));
    $this->assertSame('a', $field->value());
  }

  public function testBufferExposesTheLiveQuery(): void {
    $field = new Suggest(['alpha']);

    $field->handle(Key::char('a'));

    $this->assertSame('a', $field->buffer());
  }

  public function testCancel(): void {
    $field = new Suggest(['x', 'y']);

    $field->handle(Key::named(KeyName::Escape));

    $this->assertTrue($field->isCancelled());
  }

  public function testSpaceAppendsToBuffer(): void {
    $field = new Suggest(['x', 'y']);

    $field->handle(Key::char('a'));
    $field->handle(Key::named(KeyName::Space));

    $this->assertSame('a ', $field->value());
  }

  public function testFuzzyMatchesNonContiguousSubsequence(): void {
    $field = new Suggest(['GitHub Actions', 'GitLab CI', 'CircleCI']);

    $value = FieldRunner::run($field, ArrayKeyStream::of('gha', Key::named(KeyName::Down), Key::named(KeyName::Enter)));

    $this->assertSame('GitHub Actions', $value);
  }

  public function testRanksPrefixAheadOfLooserSubsequence(): void {
    $field = new Suggest(['Alpha', 'Beta', 'Palace']);

    // "pa" is a prefix of Palace but only a scattered subsequence of Alpha, so
    // Palace ranks first and the first Down lands on it.
    $field->handle(Key::char('p'));
    $field->handle(Key::char('a'));
    $field->handle(Key::named(KeyName::Down));

    $this->assertSame('Palace', $field->value());
  }

  public function testHighlightsMatchedCharacters(): void {
    $theme = new DefaultTheme();
    $field = new Suggest(['Alpha', 'Beta', 'Palace']);

    $field->handle(Key::char('p'));
    $field->handle(Key::char('a'));
    $view = $field->view($theme);

    // The matched "Pa" prefix is themed as a match run; the label is intact
    // once the styling is stripped.
    $this->assertStringContainsString($theme->fieldEntryMatch('Pa'), $view);
    $this->assertStringContainsString('Palace', Ansi::strip($view));
  }

  public function testShowsHighlightedSuggestionDescription(): void {
    $field = new Suggest(['apple', 'apricot'], '', NULL, ['apple' => 'Crisp and sweet.', 'apricot' => 'Small and tart.']);

    // With nothing highlighted yet (cursor detached), no description shows.
    $this->assertStringNotContainsString('Crisp and sweet.', Ansi::strip($field->view(new DefaultTheme())));

    $field->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Crisp and sweet.', $view);
    $this->assertStringNotContainsString('Small and tart.', $view);

    $field->handle(Key::named(KeyName::Down));
    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Small and tart.', $view);
    $this->assertStringNotContainsString('Crisp and sweet.', $view);
  }

  public function testOmitsDescriptionForSuggestionWithoutEntry(): void {
    $field = new Suggest(['apple', 'pear'], '', NULL, ['apple' => 'Crisp and sweet.']);

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));

    // The highlighted Pear has no description entry, so nothing is appended.
    $this->assertStringNotContainsString('Crisp', Ansi::strip($field->view(new DefaultTheme())));
  }

  public function testRejectsNonPositivePageSize(): void {
    $this->assertRejectsNonPositivePageSize(static fn(int $size): Suggest => new Suggest(['x'], page_size: $size), 0);
  }

  public function testPagesLongSuggestionList(): void {
    // The highlight starts detached (-1), so three Downs reach the third item.
    $this->assertPagesAndFollowsCursor(static fn(int $size): Suggest => new Suggest(array_values(self::pagingOptions()), page_size: $size), 3);
  }

  public function testHints(): void {
    $labels = array_map(static fn(Hint $hint): string => $hint->label, (new Suggest(['UTC', 'GMT']))->hints());

    $this->assertSame(['move', 'accept', 'cancel'], $labels);
  }

  public function testPlaceholderGhostsAnEmptyQueryOnly(): void {
    $field = (new Suggest(['Pear', 'Plum']))->setPlaceholder('Type to filter');
    $theme = new DefaultTheme();

    $this->assertStringContainsString('Type to filter', Ansi::strip($field->view($theme)));

    $field->handle(Key::char('P'));

    $this->assertStringNotContainsString('Type to filter', Ansi::strip($field->view($theme)));
  }

  public function testPlaceholderNeverCompetesWithGhostText(): void {
    $field = (new Suggest(['Apple'], '', NULL, [], TRUE))->setPlaceholder('Type to filter');
    $theme = new DefaultTheme();

    // Both occupy the one slot after the caret, but a completion needs a typed
    // query and a placeholder an empty one, so the slot is never contested.
    $this->assertStringContainsString('Type to filter', Ansi::strip($field->queryLine($theme)));

    $field->handle(Key::char('a'));
    $line = Ansi::strip($field->queryLine($theme));
    $this->assertStringContainsString('pple', $line);
    $this->assertStringNotContainsString('Type to filter', $line);
  }

  public function testGhostTextIsOptIn(): void {
    $field = new Suggest(['Apple', 'Apricot']);

    $field->handle(Key::char('a'));

    // Without the opt-in the query line carries no dimmed suffix, and the keys
    // that would accept one are inert.
    $view = $field->view(new DefaultTheme());
    $this->assertStringNotContainsString("\033[90m", $view);

    $field->handle(Key::named(KeyName::Tab));
    $field->handle(Key::named(KeyName::Right));
    $this->assertSame('a', $field->value());
  }

  public function testGhostTextRendersDimmedSuffix(): void {
    $field = new Suggest(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $field->handle(Key::char('a'));
    $field->handle(Key::char('p'));

    // The leading candidate's remainder is previewed dimmed (SGR 90) after the
    // caret, while the value stays the typed query until it is accepted.
    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('ple', $view);
    $this->assertStringContainsString("\033[90m", $view);
    $this->assertSame('ap', $field->value());
  }

  public function testTabAcceptsGhostText(): void {
    $field = new Suggest(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of('ap', Key::named(KeyName::Tab), Key::named(KeyName::Enter)));

    // Accepting adopts the candidate's own casing.
    $this->assertSame('Apple', $value);
  }

  public function testRightAcceptsGhostText(): void {
    $field = new Suggest(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $value = FieldRunner::run($field, ArrayKeyStream::of('ap', Key::named(KeyName::Right), Key::named(KeyName::Enter)));

    $this->assertSame('Apple', $value);
  }

  public function testAcceptingGhostTextKeepsTheListAvailable(): void {
    $field = new Suggest(['Apple', 'Apple pie', 'Apricot'], '', NULL, [], TRUE);

    $field->handle(Key::char('a'));
    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('Apple', $field->value());

    // The completion re-queries rather than selecting: the narrowed list is
    // still open and still arrows into.
    $view = Ansi::strip($field->view(new DefaultTheme()));
    $this->assertStringContainsString('Apple pie', $view);
    $this->assertStringNotContainsString('Apricot', $view);

    $field->handle(Key::named(KeyName::Down));
    $field->handle(Key::named(KeyName::Down));
    $this->assertSame('Apple pie', $field->value());
  }

  public function testGhostTextSuppressedWhileSuggestionHighlighted(): void {
    $field = new Suggest(['Apple', 'Apricot'], '', NULL, [], TRUE);

    $field->handle(Key::char('a'));
    $this->assertStringContainsString("\033[90m", $field->view(new DefaultTheme()));

    // Arrowing into the list makes the highlighted row the live value, so a
    // preview of the typed query would contradict it.
    $field->handle(Key::named(KeyName::Down));
    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));

    // Tab and Right stay inert while a row is highlighted.
    $field->handle(Key::named(KeyName::Tab));
    $field->handle(Key::named(KeyName::Right));
    $this->assertSame('Apple', $field->value());
  }

  public function testGhostTextSuppressedWithoutColour(): void {
    $theme = new DefaultTheme(76, ['color' => FALSE]);
    $field = new Suggest(['Apricot'], '', NULL, [], TRUE);

    $field->handle(Key::char('a'));

    // Without colour the preview cannot be dimmed, so it is dropped rather than
    // rendered as plain text indistinguishable from the typed query. The
    // suggestion itself still lists below, and no escapes leak into the line.
    $this->assertSame('a' . $theme->fieldCaret(), $field->queryLine($theme));
    $this->assertStringNotContainsString("\033", $field->view($theme));
  }

  public function testGhostTextCompletesPrefixesNotFuzzyMatches(): void {
    $field = new Suggest(['Green apple'], '', NULL, [], TRUE);

    $field->handle(Key::char('g'));
    $field->handle(Key::char('a'));

    // "ga" is a scattered subsequence, so the row is listed but there is no
    // suffix to draw after the caret.
    $view = $field->view(new DefaultTheme());
    $this->assertStringContainsString('Green apple', Ansi::strip($view));
    $this->assertStringNotContainsString("\033[90m", $view);
  }

  public function testFullyTypedSuggestionHasNoGhostText(): void {
    $field = new Suggest(['Fig'], '', NULL, [], TRUE);

    $field->handle(Key::char('f'));
    $field->handle(Key::char('i'));
    $field->handle(Key::char('g'));

    // The query already equals the only candidate; nothing is left to preview.
    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));
  }

  public function testEmptyQueryShowsNoGhostText(): void {
    // With nothing typed there is no prefix to complete.
    $field = new Suggest(['Apple'], '', NULL, [], TRUE);

    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));
  }

  public function testGhostTextIsUnicodeAware(): void {
    // Folding is per code point, so a non-ASCII prefix matches and the suffix
    // renders whole rather than splitting mid-character.
    $field = new Suggest(['Éclair'], '', NULL, [], TRUE);

    $field->handle(Key::char('é'));
    $this->assertStringContainsString('clair', $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('Éclair', $field->value());
  }

  public function testGhostTextCompletesQuerySourcedRows(): void {
    $field = new Suggest([], '', NULL, [], TRUE);
    $field->driveByQuery();

    $field->handle(Key::char('p'));
    $field->applyQuery('p', Option::list(['Pepper' => 'Pepper', 'Potato' => 'Potato']));

    // A query source's rows are already the answer and are never ranked again
    // locally, so the preview is simply their first prefix match.
    $this->assertStringContainsString('epper', $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('Pepper', $field->value());
  }

  public function testGhostTextSuppressedWhileQueryIsInFlight(): void {
    $theme = new DefaultTheme();
    $field = new Suggest([], '', NULL, [], TRUE);
    $field->driveByQuery();
    $field->applyQuery('', Option::list(['Apricot' => 'Apricot']));

    $field->handle(Key::char('a'));
    $this->assertStringContainsString("\033[90m", $field->queryLine($theme));

    // The rows still held answer the previous query, and the list showing them
    // has already given way to the loading indicator; previewing one of them
    // would put back the answer being withdrawn.
    $field->beginQuery();
    $this->assertSame('a' . $theme->fieldCaret(), $field->queryLine($theme));

    // Once the new rows settle the preview returns, drawn from them.
    $field->applyQuery('a', Option::list(['Apple' => 'Apple']));
    $this->assertStringContainsString('pple', $field->queryLine($theme));
  }

  public function testGhostTextBacksOffWhenTheQueryStopsMatching(): void {
    $field = new Suggest(['Apple'], '', NULL, [], TRUE);

    $field->handle(Key::char('a'));
    $this->assertStringContainsString("\033[90m", $field->view(new DefaultTheme()));

    // A typo drops every prefix candidate, so the preview disappears and Tab
    // leaves the query untouched.
    $field->handle(Key::char('z'));
    $this->assertStringNotContainsString("\033[90m", $field->view(new DefaultTheme()));

    $field->handle(Key::named(KeyName::Tab));
    $this->assertSame('az', $field->value());
  }

}
