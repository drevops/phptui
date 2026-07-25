<?php

declare(strict_types=1);

namespace DrevOps\Tui\Tests\Unit\Render;

use DrevOps\Tui\Render\Ansi;
use DrevOps\Tui\Render\Markup;
use DrevOps\Tui\Render\MarkupKind;
use DrevOps\Tui\Render\MarkupSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the lightweight markup parser.
 */
#[CoversClass(Markup::class)]
#[CoversClass(MarkupSegment::class)]
#[Group('render')]
final class MarkupTest extends TestCase {

  public function testParsePlainText(): void {
    $lines = Markup::parse('just text', FALSE);

    $this->assertCount(1, $lines);
    $this->assertFalse($lines[0]->bullet);
    $this->assertEquals([new MarkupSegment(MarkupKind::Text, 'just text')], $lines[0]->segments);
  }

  public function testParseSplitsPhysicalLines(): void {
    $lines = Markup::parse("one\ntwo", FALSE);

    $this->assertCount(2, $lines);
    $this->assertSame('one', $lines[0]->segments[0]->text);
    $this->assertSame('two', $lines[1]->segments[0]->text);
  }

  public function testParseLinkInBothModes(): void {
    foreach ([FALSE, TRUE] as $markdown) {
      $lines = Markup::parse('see [Orchard](https://example.com/orchard) now', $markdown);
      $segments = $lines[0]->segments;

      $this->assertCount(3, $segments);
      $this->assertSame(MarkupKind::Text, $segments[0]->kind);
      $this->assertSame('see ', $segments[0]->text);
      $this->assertSame(MarkupKind::Link, $segments[1]->kind);
      $this->assertSame('Orchard', $segments[1]->text);
      $this->assertSame('https://example.com/orchard', $segments[1]->url);
      $this->assertSame(' now', $segments[2]->text);
    }
  }

  public function testParseLeavesNonUrlBracketsLiteral(): void {
    $lines = Markup::parse('pack it [see step 3](later)', TRUE);

    $this->assertCount(1, $lines[0]->segments);
    $this->assertSame(MarkupKind::Text, $lines[0]->segments[0]->kind);
    $this->assertSame('pack it [see step 3](later)', $lines[0]->segments[0]->text);
  }

  #[DataProvider('dataProviderParseMarkdownMarkersAreLiteralWhenOff')]
  public function testParseMarkdownMarkersAreLiteralWhenOff(string $source): void {
    $lines = Markup::parse($source, FALSE);

    $this->assertCount(1, $lines[0]->segments);
    $this->assertSame(MarkupKind::Text, $lines[0]->segments[0]->kind);
    $this->assertSame($source, $lines[0]->segments[0]->text);
  }

  public static function dataProviderParseMarkdownMarkersAreLiteralWhenOff(): \Iterator {
    yield ['**bold**'];
    yield ['*emphasis*'];
    yield ['`code`'];
    yield ['- bullet'];
  }

  public function testParseBold(): void {
    $segments = Markup::parse('a **two words** b', TRUE)[0]->segments;

    $this->assertSame(MarkupKind::Bold, $segments[1]->kind);
    $this->assertSame('two words', $segments[1]->text);
  }

  public function testParseEmphasis(): void {
    $segments = Markup::parse('a *ripe* pear', TRUE)[0]->segments;

    $this->assertSame(MarkupKind::Emphasis, $segments[1]->kind);
    $this->assertSame('ripe', $segments[1]->text);
  }

  public function testParseEmphasisIgnoresSpacedAsterisks(): void {
    $segments = Markup::parse('2 * 3 * 4', TRUE)[0]->segments;

    $this->assertCount(1, $segments);
    $this->assertSame('2 * 3 * 4', $segments[0]->text);
  }

  public function testParseInlineCode(): void {
    $segments = Markup::parse('run `harvest` today', TRUE)[0]->segments;

    $this->assertSame(MarkupKind::Code, $segments[1]->kind);
    $this->assertSame('harvest', $segments[1]->text);
  }

  public function testParseBulletList(): void {
    $lines = Markup::parse("- apples\n- pears", TRUE);

    $this->assertTrue($lines[0]->bullet);
    $this->assertSame('apples', $lines[0]->segments[0]->text);
    $this->assertTrue($lines[1]->bullet);
    $this->assertSame('pears', $lines[1]->segments[0]->text);
  }

  public function testParseBulletKeepsInlineMarkup(): void {
    $line = Markup::parse('* **Ripe** fruit', TRUE)[0];

    $this->assertTrue($line->bullet);
    $this->assertSame(MarkupKind::Bold, $line->segments[0]->kind);
    $this->assertSame('Ripe', $line->segments[0]->text);
  }

  public function testLinksLeavesPlainTextUntouched(): void {
    $this->assertSame('no links here', Markup::links('no links here', TRUE));
    $this->assertSame('brackets [only]', Markup::links('brackets [only]', TRUE));
  }

  public function testLinksResolvesToHyperlinkWithColor(): void {
    $out = Markup::links('open [Basket](https://example.com/basket)', TRUE);

    $this->assertSame('open ' . Ansi::link('https://example.com/basket', 'Basket'), $out);
  }

  public function testLinksDegradesWithoutColor(): void {
    $this->assertSame('open Basket (https://example.com/basket)', Markup::links('open [Basket](https://example.com/basket)', FALSE));
  }

  public function testLinksStripsControlBytesFromTarget(): void {
    // A control byte in the target must not survive into the plain degrade,
    // where it would inject a raw escape into the printed output.
    $out = Markup::links("open [Basket](https://example.com/b\033[31m)", FALSE);

    $this->assertSame('open Basket (https://example.com/b[31m)', $out);
    $this->assertStringNotContainsString("\033", $out);
  }

  #[DataProvider('dataProviderHyperlink')]
  public function testHyperlink(string $text, string $url, bool $color, string $expected): void {
    $this->assertSame($expected, Markup::hyperlink($text, $url, $color));
  }

  public static function dataProviderHyperlink(): \Iterator {
    yield 'colour on' => ['Grapes', 'https://example.com/grapes', TRUE, "\033]8;;https://example.com/grapes\007Grapes\033]8;;\007"];
    yield 'colour off' => ['Grapes', 'https://example.com/grapes', FALSE, 'Grapes (https://example.com/grapes)'];
    yield 'empty label uses url' => ['', 'https://example.com/grapes', TRUE, "\033]8;;https://example.com/grapes\007https://example.com/grapes\033]8;;\007"];
    yield 'label equal to url is not doubled' => ['https://example.com', 'https://example.com', FALSE, 'https://example.com'];
    yield 'empty url is plain text' => ['Grapes', '', FALSE, 'Grapes'];
  }

  #[DataProvider('dataProviderWidth')]
  public function testWidth(string $source, bool $markdown, bool $color, int $expected): void {
    $this->assertSame($expected, Markup::width($source, $markdown, $color));
  }

  public static function dataProviderWidth(): \Iterator {
    yield 'plain' => ['carrot', FALSE, TRUE, 6];
    yield 'link colour on measures label' => ['[Kale](https://example.com/kale)', FALSE, TRUE, 4];
    yield 'link colour off measures degrade' => ['[Kale](https://example.com/k)', FALSE, FALSE, strlen('Kale (https://example.com/k)')];
    yield 'bold measures inner text' => ['**Fresh**', TRUE, TRUE, 5];
    yield 'bullet adds marker width' => ['- pears', TRUE, TRUE, 7];
    yield 'widest of several lines' => ["a\nlonger line", FALSE, TRUE, 11];
  }

}
