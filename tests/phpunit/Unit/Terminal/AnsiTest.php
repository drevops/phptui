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
