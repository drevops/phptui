<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Utils;

use DrevOps\Tui\Utils\Strings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the UTF-8 helpers in the mbstring and fallback branches.
 */
#[CoversClass(Strings::class)]
#[Group('utils')]
final class StringsTest extends TestCase {

  protected function tearDown(): void {
    Strings::useMbstring(NULL);
    parent::tearDown();
  }

  #[DataProvider('dataProviderLength')]
  public function testLength(string $text, int $expected): void {
    Strings::useMbstring(TRUE);
    $this->assertSame($expected, Strings::length($text));

    Strings::useMbstring(FALSE);
    $this->assertSame($expected, Strings::length($text));
  }

  public static function dataProviderLength(): \Iterator {
    yield 'empty' => ['', 0];
    yield 'ascii' => ['Pear', 4];
    yield 'accented' => ['pêche', 5];
    yield 'emoji' => ['🍎🍐', 2];
    yield 'mixed' => ['Grape 🍇', 7];
  }

  #[DataProvider('dataProviderSubstr')]
  public function testSubstr(string $text, int $start, ?int $length, string $expected): void {
    Strings::useMbstring(TRUE);
    $this->assertSame($expected, Strings::substr($text, $start, $length));

    Strings::useMbstring(FALSE);
    $this->assertSame($expected, Strings::substr($text, $start, $length));
  }

  public static function dataProviderSubstr(): \Iterator {
    yield 'empty' => ['', 0, NULL, ''];
    yield 'ascii middle' => ['Pear', 1, 2, 'ea'];
    yield 'prefix' => ['🍎🍐🍒🍋', 0, 2, '🍎🍐'];
    yield 'to end' => ['🍎🍐🍒🍋', 1, NULL, '🍐🍒🍋'];
    yield 'negative start' => ['🍎🍐🍒🍋', -2, NULL, '🍒🍋'];
    yield 'negative length' => ['🍎🍐🍒🍋', 0, -1, '🍎🍐🍒'];
    yield 'length past end' => ['🍎🍐🍒🍋', 3, 5, '🍋'];
    yield 'start past end' => ['🍎🍐🍒🍋', 9, 2, ''];
    yield 'negative start clamped' => ['🍎🍐🍒🍋', -9, 2, '🍎🍐'];
    yield 'negative length past start' => ['🍎🍐🍒🍋', 0, -9, ''];
  }

  #[DataProvider('dataProviderLower')]
  public function testLower(string $text, string $expected_mbstring, string $expected_fallback): void {
    Strings::useMbstring(TRUE);
    $this->assertSame($expected_mbstring, Strings::lower($text));

    Strings::useMbstring(FALSE);
    $this->assertSame($expected_fallback, Strings::lower($text));
  }

  public static function dataProviderLower(): \Iterator {
    yield 'ascii' => ['PEAR', 'pear', 'pear'];
    yield 'accented' => ['PÊCHE', 'pêche', 'pÊche'];
    yield 'cyrillic' => ['ЯБЛОКО', 'яблоко', 'ЯБЛОКО'];
    yield 'emoji' => ['Tomato 🍅', 'tomato 🍅', 'tomato 🍅'];
  }

  #[DataProvider('dataProviderSplit')]
  public function testSplit(string $text, array $expected): void {
    Strings::useMbstring(TRUE);
    $this->assertSame($expected, Strings::split($text));

    Strings::useMbstring(FALSE);
    $this->assertSame($expected, Strings::split($text));
  }

  public static function dataProviderSplit(): \Iterator {
    yield 'empty' => ['', []];
    yield 'ascii' => ['pea', ['p', 'e', 'a']];
    yield 'emoji between ascii' => ['🍎x🍐', ['🍎', 'x', '🍐']];
    yield 'accented' => ['pêche', ['p', 'ê', 'c', 'h', 'e']];
  }

  #[DataProvider('dataProviderWrap')]
  public function testWrap(string $text, int $width, array $expected): void {
    Strings::useMbstring(TRUE);
    $this->assertSame($expected, Strings::wrap($text, $width));

    Strings::useMbstring(FALSE);
    $this->assertSame($expected, Strings::wrap($text, $width));
  }

  public static function dataProviderWrap(): \Iterator {
    yield 'empty' => ['', 10, []];
    yield 'whitespace only' => ['   ', 10, []];
    yield 'single word fits' => ['Pear', 10, ['Pear']];
    yield 'words fit on one line' => ['Ripe sweet Pear', 20, ['Ripe sweet Pear']];
    yield 'wraps on width' => ['Ripe sweet Pear', 10, ['Ripe sweet', 'Pear']];
    yield 'collapses whitespace' => ["Ripe\t  sweet   Pear", 20, ['Ripe sweet Pear']];
    yield 'hard-splits long word' => ['Pomegranate', 5, ['Pomeg', 'ranat', 'e']];
    yield 'long word then word' => ['Pomegranate Pear', 5, ['Pomeg', 'ranat', 'e', 'Pear']];
    yield 'word then long word' => ['Pear Pomegranate', 5, ['Pear', 'Pomeg', 'ranat', 'e']];
    yield 'zero width joins to one line' => ['Ripe Pear', 0, ['Ripe Pear']];
    yield 'negative width joins to one line' => ['Ripe Pear', -3, ['Ripe Pear']];
    yield 'multibyte words' => ['pêche mûre', 5, ['pêche', 'mûre']];
    yield 'emoji words' => ['🍎 🍐 🍒', 3, ['🍎 🍐', '🍒']];
  }

  public function testMalformedInputFallsBackToBytes(): void {
    Strings::useMbstring(FALSE);

    $this->assertSame(["\xC3", '('], Strings::split("\xC3("));
    $this->assertSame(2, Strings::length("\xC3("));
    $this->assertSame("\xC3", Strings::substr("\xC3(", 0, 1));
  }

  public function testDetectsMbstring(): void {
    Strings::useMbstring(NULL);

    // The fallback branch lowercases ASCII only, so the result reveals which
    // branch the detection selected.
    $this->assertSame(function_exists('mb_strlen') ? 'pêche' : 'pÊche', Strings::lower('PÊCHE'));
  }

  #[DataProvider('dataProviderInterpolate')]
  public function testInterpolate(string $template, array $values, string $expected): void {
    $this->assertSame($expected, Strings::interpolate($template, $values));
  }

  public static function dataProviderInterpolate(): \Iterator {
    yield 'no tokens' => ['Fresh produce', ['fruit' => 'pear'], 'Fresh produce'];
    yield 'single token' => ['A {{fruit}} a day', ['fruit' => 'pear'], 'A pear a day'];
    yield 'spaced token' => ['{{ fruit }} basket', ['fruit' => 'pear'], 'pear basket'];
    yield 'repeated token' => ['{{fruit}} and {{fruit}}', ['fruit' => 'plum'], 'plum and plum'];
    yield 'multiple tokens' => ['{{fruit}} or {{veg}}', ['fruit' => 'pear', 'veg' => 'kale'], 'pear or kale'];
    yield 'missing token resolves empty' => ['a-{{nope}}-b', [], 'a--b'];
    yield 'non-scalar token resolves empty' => ['{{list}}', ['list' => ['x']], ''];
    yield 'numeric value coerces' => ['count: {{n}}', ['n' => 3], 'count: 3'];
  }

}
