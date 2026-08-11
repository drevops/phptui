<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Terminal;

use DrevOps\Tui\Terminal\Ansi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ANSI helpers.
 */
#[CoversClass(Ansi::class)]
#[Group('terminal')]
final class AnsiTest extends TestCase {

  public function testStyle(): void {
    $this->assertSame("\033[1;32mhi\033[0m", Ansi::style('hi', '1;32'));
    $this->assertSame('hi', Ansi::style('hi', ''));
  }

  public function testWash(): void {
    // An empty background returns the text unchanged.
    $this->assertSame("a\nb", Ansi::wash("a\nb", ''));

    // A background re-opens on each line and erases to its end.
    $washed = Ansi::wash("a\nb", '44');
    $this->assertStringContainsString("\033[44m", $washed);
    $this->assertStringContainsString("\033[K", $washed);

    // A reset re-opens the wash, so gaps between spans stay filled.
    $this->assertStringContainsString("\033[0m\033[44m", Ansi::wash("\033[1mx\033[0m", '44'));
  }

  public function testStripAndWidth(): void {
    $styled = Ansi::style('hello', '32');

    $this->assertSame('hello', Ansi::strip($styled));
    $this->assertSame(5, Ansi::width($styled));
    $this->assertSame(3, Ansi::width('❯ x'));
  }

  public function testLink(): void {
    $link = Ansi::link('Orchard', 'https://example.com/orchard');

    $this->assertSame("\033]8;;https://example.com/orchard\007Orchard\033]8;;\007", $link);

    // The escape adds no visible width: a linked label measures as its text.
    $this->assertSame('Orchard', Ansi::strip($link));
    $this->assertSame(7, Ansi::width($link));

    // A hyperlink still styled with colour strips and measures cleanly.
    $styled = Ansi::style($link, '1;36');
    $this->assertSame('Orchard', Ansi::strip($styled));
    $this->assertSame(7, Ansi::width($styled));
  }

  public function testLinkStripsControlBytes(): void {
    // A BEL or ESC smuggled into the URL or text would break out of the OSC 8
    // wrapper; both are dropped before the sequence is built.
    $link = Ansi::link("Buy\033]8;;evil\007now", "https://example.com/a\007\033[31m");

    $this->assertSame("\033]8;;https://example.com/a[31m\007Buy]8;;evilnow\033]8;;\007", $link);
    // The wrapper survives intact, so the whole thing strips to its text alone.
    $this->assertSame('Buy]8;;evilnow', Ansi::strip($link));
  }

  public function testStripControl(): void {
    $this->assertSame('clean', Ansi::stripControl("cl\033ea\007n"));
    $this->assertSame('abc', Ansi::stripControl("a\x00b\x7fc"));
    $this->assertSame('keeps spaces', Ansi::stripControl('keeps spaces'));
  }

  #[DataProvider('dataProviderSanitize')]
  public function testSanitize(string $text, string $expected): void {
    $this->assertSame($expected, Ansi::sanitize($text));
  }

  public static function dataProviderSanitize(): \Iterator {
    yield 'text with nothing to filter is untouched' => ['Deliveries leave at dawn.', 'Deliveries leave at dawn.'];
    yield 'a screen clear is dropped, its text kept' => ["Deliveries \033[2J leave at dawn.", 'Deliveries [2J leave at dawn.'];
    yield 'a colour sequence is dropped' => ["\033[31mApricot\033[0m", '[31mApricot[0m'];
    yield 'a cursor move is dropped' => ["Pears\033[10;20HPlums", 'Pears[10;20HPlums'];
    yield 'a hyperlink wrapper loses its escapes and its bell' => ["\033]8;;https://example.com\007Figs\033]8;;\007", ']8;;https://example.comFigs]8;;'];
    yield 'a newline is text and stays' => ["Apples\nOranges", "Apples\nOranges"];
    yield 'a tab is text and stays' => ["Apples\tOranges", "Apples\tOranges"];
    yield 'a Windows line ending folds to a newline' => ["Apples\r\nOranges", "Apples\nOranges"];
    yield 'a lone carriage return folds to a newline' => ["Apples\rOranges", "Apples\nOranges"];
    yield 'a doubled carriage return folds to two newlines' => ["Apples\r\rOranges", "Apples\n\nOranges"];
    yield 'a null byte is dropped' => ["App\x00les", 'Apples'];
    yield 'a bell is dropped' => ["App\007les", 'Apples'];
    yield 'a vertical tab is dropped' => ["App\x0bles", 'Apples'];
    yield 'a delete is dropped' => ["App\x7fles", 'Apples'];
    // A terminal in 8-bit mode opens a sequence on U+009B, so it goes the same
    // way the two-byte ESC-[ that introduces the same sequence does.
    yield 'an eight-bit control introducer is dropped' => ["App\u{009B}2Jles", 'App2Jles'];
    yield 'an eight-bit string terminator is dropped' => ["App\u{009C}les", 'Apples'];
    yield 'a printable character above the controls stays' => ["App\u{00A1}les", "App\u{00A1}les"];
    // Encoding the introducer as a bare byte makes the text invalid UTF-8,
    // which would otherwise carry it straight past the filter.
    yield 'a bare eight-bit introducer is dropped' => ["App\x9B2Jles", 'App2Jles'];
    yield 'a bare continuation byte goes with it' => ["App\x80les", 'Apples'];
    yield 'multi-byte text is untouched' => ['Груші та сливи', 'Груші та сливи'];
    yield 'a character whose bytes span the control range is untouched' => ['Ω≈ç√', 'Ω≈ç√'];
    yield 'nothing in is nothing out' => ['', ''];
  }

  #[DataProvider('dataProviderSanitizeValue')]
  public function testSanitizeValue(mixed $value, mixed $expected): void {
    $this->assertSame($expected, Ansi::sanitizeValue($value));
  }

  public static function dataProviderSanitizeValue(): \Iterator {
    yield 'a string is filtered' => ["Apri\033[2Jcots", 'Apri[2Jcots'];
    yield 'a list is filtered item by item' => [["Ap\033[2Jples", "Pe\007ars"], ['Ap[2Jples', 'Pears']];
    // A key addresses an entry rather than being read, and filtering two keys
    // into one would drop an entry.
    yield 'a string key is left as it is' => [["Ba\033[2Jsket" => "Pl\007ums"], ["Ba\033[2Jsket" => 'Plums']];
    yield 'two keys that would collide both survive' => [['a' => 1, "a\007" => 2], ['a' => 1, "a\007" => 2]];
    yield 'an integer key is left as it is' => [[3 => "Pl\007ums"], [3 => 'Plums']];
    yield 'nesting is walked to its leaves' => [[['a' => ["Fi\007gs"]]], [['a' => ['Figs']]]];
    yield 'an integer is not text' => [42, 42];
    yield 'a float is not text' => [1.5, 1.5];
    yield 'a boolean is not text' => [TRUE, TRUE];
    yield 'nothing is not text' => [NULL, NULL];
    yield 'an empty list stays empty' => [[], []];
  }

  public function testSanitizeValueLeavesAnObjectAlone(): void {
    $object = new \stdClass();

    $this->assertSame($object, Ansi::sanitizeValue($object));
  }

  public function testStripHyperlinkTerminators(): void {
    $esc = "\033";

    // A BEL-terminated hyperlink.
    $this->assertSame('Apple', Ansi::strip($esc . ']8;;https://example.com/apple' . "\007" . 'Apple' . $esc . ']8;;' . "\007"));

    // An ST-terminated (ESC-backslash) hyperlink.
    $this->assertSame('Pear', Ansi::strip($esc . ']8;;https://example.com/pear' . $esc . '\\Pear' . $esc . ']8;;' . $esc . '\\'));
  }

  public function testBlockWidth(): void {
    $this->assertSame(0, Ansi::blockWidth([]));
    $this->assertSame(5, Ansi::blockWidth(['ab', Ansi::style('hello', '32'), 'x']));
    $this->assertSame(7, Ansi::blockWidth([Ansi::link('Orchard', 'https://example.com/a')]));
  }

  public function testAlignRight(): void {
    $this->assertSame('ab   Z', Ansi::alignRight('ab', 'Z', 6));

    $styled = Ansi::alignRight('ab', Ansi::style('Z', '7'), 6);
    $this->assertSame(6, Ansi::width($styled));
  }

  public function testAlignRightMinimumPad(): void {
    $this->assertSame('abcdef X', Ansi::alignRight('abcdef', 'X', 3));
  }

  #[DataProvider('dataProviderSlice')]
  public function testSlice(string $text, int $width, string $expected): void {
    $this->assertSame($expected, Ansi::slice($text, $width));
  }

  public static function dataProviderSlice(): \Iterator {
    $esc = "\033";

    yield 'plain text is a plain slice' => ['abcdef', 3, 'abc'];
    yield 'plain text that fits is untouched' => ['abc', 5, 'abc'];
    yield 'multi-byte counts characters' => ['дуже довгий', 4, 'дуже'];
    yield 'nothing survives a width of nothing' => ['abcdef', 0, ''];
    yield 'nor a width below it' => ['abcdef', -2, ''];
    yield 'a style that fits is untouched' => [$esc . '[36mab' . $esc . '[0m', 5, $esc . '[36mab' . $esc . '[0m'];
    yield 'a style open at the cut is closed' => [$esc . '[36mabcdef' . $esc . '[0m', 3, $esc . '[36mabc' . $esc . '[0m'];
    yield 'a style closed before the cut is not reopened' => [$esc . '[31mab' . $esc . '[0mcdef', 4, $esc . '[31mab' . $esc . '[0mcd'];
    yield 'a sequence spanning the cut is dropped whole' => [$esc . '[31mabc' . $esc . '[0mdef', 2, $esc . '[31mab' . $esc . '[0m'];
    yield 'each span keeps its own style' => [$esc . '[31mab' . $esc . '[0m' . $esc . '[32mcd' . $esc . '[0m', 3, $esc . '[31mab' . $esc . '[0m' . $esc . '[32mc' . $esc . '[0m'];
    // A reset is written out of zeroes however many of them there are.
    yield 'a padded reset still resets' => [$esc . '[31mab' . $esc . '[00;0mcdef', 4, $esc . '[31mab' . $esc . '[00;0mcd'];
    // Only the sequences that style anything are tracked, so a line erase
    // travels with the row it was written on and closes nothing.
    yield 'a sequence that styles nothing is carried as it is' => [$esc . '[44mab' . $esc . '[Kcdef', 3, $esc . '[44mab' . $esc . '[Kc' . $esc . '[0m'];
    yield 'a hyperlink open at the cut is closed' => [$esc . ']8;;https://example.com/a' . "\007" . 'Orchard' . $esc . ']8;;' . "\007", 3, $esc . ']8;;https://example.com/a' . "\007" . 'Orc' . $esc . ']8;;' . "\007"];
    yield 'an ST-terminated hyperlink too' => [$esc . ']8;;https://example.com/a' . $esc . '\\Orchard' . $esc . ']8;;' . $esc . '\\', 3, $esc . ']8;;https://example.com/a' . $esc . '\\Orc' . $esc . ']8;;' . "\007"];
    yield 'a hyperlink closed before the cut is not reopened' => [$esc . ']8;;https://example.com/a' . "\007" . 'Or' . $esc . ']8;;' . "\007" . 'chard', 4, $esc . ']8;;https://example.com/a' . "\007" . 'Or' . $esc . ']8;;' . "\007" . 'ch'];
    yield 'a styled hyperlink closes both' => [$esc . '[1;36m' . $esc . ']8;;https://example.com/a' . "\007" . 'Orchard' . $esc . ']8;;' . "\007" . $esc . '[0m', 3, $esc . '[1;36m' . $esc . ']8;;https://example.com/a' . "\007" . 'Orc' . $esc . ']8;;' . "\007" . $esc . '[0m'];
  }

  public function testSliceCutsToExactlyTheVisibleWidth(): void {
    $link = Ansi::style(Ansi::link('Orchard', 'https://example.com/orchard'), '1;36');

    $this->assertSame(4, Ansi::width(Ansi::slice($link, 4)));
    $this->assertSame('Orch', Ansi::strip(Ansi::slice($link, 4)));
  }

}
