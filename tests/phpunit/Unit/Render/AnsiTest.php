<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Ansi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ANSI helpers.
 */
#[CoversClass(Ansi::class)]
#[Group('tui')]
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

}
