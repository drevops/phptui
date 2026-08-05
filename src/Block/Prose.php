<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\MarkupElementsInterface;
use DrevOps\Tui\Terminal\Markup as Parser;
use DrevOps\Tui\Terminal\MarkupKind;
use DrevOps\Tui\Terminal\MarkupSegment;
use DrevOps\Tui\Theme\Capability\MarkdownCapableInterface;

/**
 * Text a reader reads, drawn one span at a time.
 *
 * A passage is not one string with one style: a word may be emphatic, a
 * fragment may be code, and a target may be somewhere to go. So it is parsed
 * into spans and each span drawn by the element that owns it, which is what
 * lets a theme restyle what is emphatic everywhere it appears rather than in
 * the one place that happened to compose it.
 *
 * It is not a block. Several blocks carry a passage - a field's explanation, a
 * standing note, the long text behind a help key - and none of them owns how
 * one is drawn, so it lives beside them rather than inside any one of them.
 *
 * @package DrevOps\Tui\Block
 */
final class Prose {

  /**
   * Draw a passage, one physical line per source line.
   *
   * @param string $source
   *   The passage; newlines separate physical lines.
   * @param \DrevOps\Tui\Block\Element\MarkupElementsInterface $theme
   *   The theme, narrowed to the elements that draw a span.
   * @param \Closure|null $plain
   *   An `fn (string $text): string` drawing the spans that carry no markup of
   *   their own, so a block keeps the element it is read through; NULL draws
   *   them as a line of markup.
   *
   * @return list<string>
   *   The drawn lines.
   */
  public static function lines(string $source, MarkupElementsInterface $theme, ?\Closure $plain = NULL): array {
    $plain ??= $theme->markupLine(...);
    $lines = [];

    foreach (Parser::parse($source, self::markdown($theme)) as $line) {
      $drawn = $line->bullet ? $plain($theme->markupBullet() . ' ') : '';

      foreach ($line->segments as $segment) {
        $drawn .= self::span($segment, $theme, $plain);
      }

      $lines[] = $drawn;
    }

    return $lines;
  }

  /**
   * Draw one span with the element that owns it.
   *
   * @param \DrevOps\Tui\Terminal\MarkupSegment $segment
   *   The span.
   * @param \DrevOps\Tui\Block\Element\MarkupElementsInterface $theme
   *   The theme.
   * @param \Closure $plain
   *   What draws a span carrying no markup of its own.
   *
   * @return string
   *   The drawn span.
   */
  protected static function span(MarkupSegment $segment, MarkupElementsInterface $theme, \Closure $plain): string {
    return match ($segment->kind) {
      MarkupKind::Bold => $theme->markupStrong($segment->text),
      MarkupKind::Emphasis => $theme->markupEmphasis($segment->text),
      MarkupKind::Code => $theme->markupCode($segment->text),
      MarkupKind::Link => $theme->markupLink($segment->text, $segment->url),
      // Every link is already a span of its own, so the styling left to do here
      // is whatever the surrounding text is drawn in.
      MarkupKind::Text => $plain($segment->text),
    };
  }

  /**
   * Whether the theme draws the markdown subset or leaves it literal.
   *
   * @param \DrevOps\Tui\Block\Element\MarkupElementsInterface $theme
   *   The theme.
   *
   * @return bool
   *   TRUE when it draws it.
   */
  protected static function markdown(MarkupElementsInterface $theme): bool {
    return $theme instanceof MarkdownCapableInterface && $theme->hasMarkdown();
  }

}
