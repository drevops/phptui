<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Field;

use DrevOps\Tui\Block\Option;
use DrevOps\Tui\Block\OptionKind;
use DrevOps\Tui\Field\Matcher;
use DrevOps\Tui\Field\MatchResult;
use DrevOps\Tui\Field\MatchTier;
use DrevOps\Tui\Utils\Strings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the fuzzy matcher.
 */
#[CoversClass(Matcher::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(MatchTier::class)]
#[Group('field')]
final class MatcherTest extends TestCase {

  protected function tearDown(): void {
    Strings::useMbstring(NULL);
    parent::tearDown();
  }

  public function testEmptyNeedleMatchesWithZeroScore(): void {
    $result = (new Matcher())->match('anything', '');

    $this->assertInstanceOf(MatchResult::class, $result);
    $this->assertSame(0, $result->score);
    $this->assertSame([], $result->positions);
  }

  #[DataProvider('dataProviderMatchRejectsNonSubsequence')]
  public function testMatchRejectsNonSubsequence(string $haystack, string $needle): void {
    $result = (new Matcher())->match($haystack, $needle);

    $this->assertNotInstanceOf(MatchResult::class, $result);
  }

  public static function dataProviderMatchRejectsNonSubsequence(): \Iterator {
    yield 'missing character' => ['GitHub Actions', 'ghz'];
    yield 'wrong order' => ['abc', 'cba'];
    yield 'needle longer than haystack' => ['ab', 'abc'];
  }

  public function testMatchRejectsQueryThatResolvesToNoCharacters(): void {
    Strings::useMbstring(FALSE);

    $this->assertNotInstanceOf(MatchResult::class, (new Matcher())->match('apple', "\xC3"));
  }

  #[DataProvider('dataProviderTighterMatchesRankAhead')]
  public function testTighterMatchesRankAhead(string $needle, string $stronger, string $weaker): void {
    $matcher = new Matcher();

    $strong = $matcher->match($stronger, $needle);
    $weak = $matcher->match($weaker, $needle);

    $this->assertInstanceOf(MatchResult::class, $strong);
    $this->assertInstanceOf(MatchResult::class, $weak);
    $this->assertGreaterThan($weak->score, $strong->score);
  }

  public static function dataProviderTighterMatchesRankAhead(): \Iterator {
    yield 'exact over prefix' => ['lon', 'lon', 'london'];
    yield 'prefix over substring' => ['lon', 'london', 'ceylon'];
    yield 'substring over subsequence' => ['lon', 'ceylon', 'lemon'];
    yield 'earlier substring over later' => ['red', 'reddit', 'shredded'];
    yield 'contiguous over scattered' => ['abc', 'zabc', 'aXbXc'];
    yield 'tighter but later over looser but earlier' => ['ab', 'xxxxxab', 'axxxxxxb'];
    // The tier folds the final sigma too, so "Ος" is exact - not just the
    // subsequence the per-character match found.
    yield 'final sigma exact over prefix' => ['ος', 'Ος', 'Οσμή'];
  }

  #[DataProvider('dataProviderMatchLocatesHighlightPositions')]
  public function testMatchLocatesHighlightPositions(string $haystack, string $needle, array $expected): void {
    $result = (new Matcher())->match($haystack, $needle);

    $this->assertInstanceOf(MatchResult::class, $result);
    $this->assertSame($expected, $result->positions);
  }

  public static function dataProviderMatchLocatesHighlightPositions(): \Iterator {
    yield 'prefix is a contiguous run' => ['London', 'lon', [0, 1, 2]];
    yield 'substring run at its offset' => ['CircleCI', 'ci', [0, 1]];
    yield 'scattered subsequence indices' => ['GitHub Actions', 'gha', [0, 3, 7]];
    yield 'multibyte substring boundary' => ['Zürich', 'ür', [1, 2]];
    yield 'multibyte trailing character' => ['Café', 'é', [3]];
    // The tight "abc" cluster at the end beats the greedy leftmost a-b-c.
    yield 'tightest embedding not the greedy one' => ['axxxxxxxabyc', 'abc', [8, 9, 11]];
    // Lowercasing "İ" expands to two code points; positions must still
    // index the original string, so "sum" lands on original indices 2-4.
    yield 'length-changing fold keeps original offsets' => ['İpsum', 'sum', [2, 3, 4]];
    // A capitalised query matches a label ending in the Greek final sigma:
    // both fold to the regular sigma.
    yield 'capital sigma matches final sigma' => ['Ος', 'ΟΣ', [0, 1]];
  }

  public function testRankValuesOrdersMatchesAndDropsMisses(): void {
    $matcher = new Matcher();

    $ranked = $matcher->rankValues(['Europe/London', 'Australia/Sydney', 'lon', 'Ceylon'], 'lon');

    $this->assertSame(['lon', 'Europe/London', 'Ceylon'], $ranked);
  }

  public function testRankValuesKeepsInputOrderForTies(): void {
    $matcher = new Matcher();

    $ranked = $matcher->rankValues(['alpha', 'aleph'], 'al');

    $this->assertSame(['alpha', 'aleph'], $ranked);
  }

  public function testRankOptionsRanksAndDropsStructuralRows(): void {
    $matcher = new Matcher();

    $options = [
      new Option('a', 'Apple'),
      new Option('', 'Fruits', '', OptionKind::Heading),
      new Option('p', 'Pineapple'),
      new Option('', '', '', OptionKind::Separator),
      new Option('g', 'Grape'),
    ];

    $ranked = $matcher->rankOptions($options, 'apple');

    $values = array_map(static fn(Option $option): string => $option->value, $ranked);
    $this->assertSame(['a', 'p'], $values);
  }

  public function testPositionsConvenienceReturnsEmptyOnMiss(): void {
    $matcher = new Matcher();

    $this->assertSame([0, 1, 2], $matcher->positions('London', 'lon'));
    $this->assertSame([], $matcher->positions('London', 'xyz'));
  }

}
