<?php

declare(strict_types=1);

namespace DrevOps\Tui\Render;

use DrevOps\Tui\Utils\Strings;

/**
 * A lightweight inline-markup parser for label, description and note text.
 *
 * Two jobs, one small grammar. Links - `[text](url)` with a real URL target -
 * are always recognised, so any surface can carry a clickable label that
 * degrades to `text (url)` where a terminal or the colour switch cannot show
 * one. With markdown enabled the grammar also covers `**bold**`, `*emphasis*`,
 * `` `code` `` and `- ` bullet lists, mapped by the theme to its style atoms.
 *
 * The parser returns a structure - physical {@see MarkupLine}s of
 * {@see MarkupSegment}s - so the theme owns the styling and this class stays
 * free of colour and glyph decisions. {@see links()}, {@see hyperlink()} and
 * {@see width()} are the flat helpers the summary and the width maths use.
 *
 * @package DrevOps\Tui\Render
 */
final class Markup {

  /**
   * Parse source text into physical lines of inline spans.
   *
   * @param string $source
   *   The source text; newlines separate physical lines.
   * @param bool $markdown
   *   Whether the full markdown subset is recognised. When FALSE only links are
   *   parsed and every other marker is left as literal text.
   *
   * @return list<\DrevOps\Tui\Render\MarkupLine>
   *   The parsed lines.
   */
  public static function parse(string $source, bool $markdown): array {
    $lines = [];

    // Fold CRLF and lone-CR endings (a Windows-authored catalog can carry them)
    // here, so every caller - links() and width() included - splits cleanly and
    // no stray carriage return reaches a Text segment.
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $source)) as $line) {
      $bullet = FALSE;

      if ($markdown && preg_match('/^[ \t]*[-*][ \t]+(.*)$/', $line, $matches) === 1) {
        $bullet = TRUE;
        $line = $matches[1];
      }

      $lines[] = new MarkupLine($bullet, self::parseInline($line, $markdown));
    }

    return $lines;
  }

  /**
   * Resolve only the links in a string, leaving all other text untouched.
   *
   * @param string $text
   *   The text.
   * @param bool $color
   *   Whether the terminal can show a clickable hyperlink; FALSE degrades each
   *   link to `text (url)`.
   *
   * @return string
   *   The text with links resolved.
   */
  public static function links(string $text, bool $color): string {
    if (!str_contains($text, '[')) {
      return $text;
    }

    $out = [];

    foreach (self::parse($text, FALSE) as $line) {
      $rendered = '';

      foreach ($line->segments as $segment) {
        $rendered .= $segment->kind === MarkupKind::Link ? self::hyperlink($segment->text, $segment->url, $color) : $segment->text;
      }

      $out[] = $rendered;
    }

    return implode("\n", $out);
  }

  /**
   * Render a single link: the OSC 8 escape, or the `text (url)` degrade.
   *
   * @param string $text
   *   The link label; empty falls back to the URL.
   * @param string $url
   *   The link target; empty returns the label as plain text.
   * @param bool $color
   *   Whether the terminal can show a clickable hyperlink.
   *
   * @return string
   *   The rendered link.
   */
  public static function hyperlink(string $text, string $url, bool $color): string {
    // Drop control bytes so the plain degrade cannot inject escapes and the
    // hyperlink cannot break its wrapper (Ansi::link() strips them too).
    $text = Ansi::stripControl($text);
    $url = Ansi::stripControl($url);
    $label = self::resolveLabel($text, $url);

    if ($url === '') {
      return $label;
    }

    if ($color) {
      return Ansi::link($label, $url);
    }

    return self::degrade($label, $url);
  }

  /**
   * The visible width of the widest physical line a source renders to.
   *
   * @param string $source
   *   The source text.
   * @param bool $markdown
   *   Whether the full markdown subset is recognised.
   * @param bool $color
   *   Whether links show as their label alone (colour on) or `text (url)`.
   *
   * @return int
   *   The widest line's visible width, in columns.
   */
  public static function width(string $source, bool $markdown, bool $color): int {
    $width = 0;

    foreach (self::parse($source, $markdown) as $line) {
      $line_width = $line->bullet ? 2 : 0;

      foreach ($line->segments as $segment) {
        $line_width += Strings::length(self::visible($segment, $color));
      }

      $width = max($width, $line_width);
    }

    return $width;
  }

  /**
   * The visible text of a segment, as it renders at the given colour setting.
   *
   * @param \DrevOps\Tui\Render\MarkupSegment $segment
   *   The segment.
   * @param bool $color
   *   Whether a link shows as its label alone (colour on) or `text (url)`.
   *
   * @return string
   *   The visible text.
   */
  protected static function visible(MarkupSegment $segment, bool $color): string {
    if ($segment->kind !== MarkupKind::Link) {
      return $segment->text;
    }

    $label = self::resolveLabel($segment->text, $segment->url);

    return $color ? $label : self::degrade($label, $segment->url);
  }

  /**
   * The label a link shows: its text, or the URL when the text is empty.
   *
   * @param string $text
   *   The link text.
   * @param string $url
   *   The link target.
   *
   * @return string
   *   The visible label.
   */
  protected static function resolveLabel(string $text, string $url): string {
    return $text === '' ? $url : $text;
  }

  /**
   * The plain-text degrade of a link: `label (url)`, or the label alone.
   *
   * Shared by {@see hyperlink()} and {@see visible()} so the rendered degrade
   * and the width measurement can never drift apart.
   *
   * @param string $label
   *   The resolved link label.
   * @param string $url
   *   The link target.
   *
   * @return string
   *   The degraded text.
   */
  protected static function degrade(string $label, string $url): string {
    return $label === $url || $url === '' ? $label : $label . ' (' . $url . ')';
  }

  /**
   * Parse one physical line into inline spans.
   *
   * @param string $text
   *   The line text (already stripped of any bullet marker).
   * @param bool $markdown
   *   Whether the full markdown subset is recognised.
   *
   * @return list<\DrevOps\Tui\Render\MarkupSegment>
   *   The spans, in order.
   */
  protected static function parseInline(string $text, bool $markdown): array {
    $segments = [];
    $length = strlen($text);
    $index = 0;
    $start = 0;

    while ($index < $length) {
      $span = self::matchSpan($text, $index, $markdown);

      if ($span === NULL) {
        $index++;

        continue;
      }

      // Close the run of plain text that led up to this span before it.
      if ($index > $start) {
        $segments[] = new MarkupSegment(MarkupKind::Text, substr($text, $start, $index - $start));
      }

      [$segment, $consumed] = $span;
      $segments[] = $segment;
      $index += $consumed;
      $start = $index;
    }

    if ($index > $start) {
      $segments[] = new MarkupSegment(MarkupKind::Text, substr($text, $start));
    }

    return $segments;
  }

  /**
   * Match one inline span starting at a byte offset.
   *
   * @param string $text
   *   The line text.
   * @param int $index
   *   The byte offset to anchor the match at.
   * @param bool $markdown
   *   Whether the full markdown subset is recognised.
   *
   * @return array{\DrevOps\Tui\Render\MarkupSegment, int}|null
   *   The span and the byte length it consumes, or NULL when no span starts at
   *   the offset.
   */
  protected static function matchSpan(string $text, int $index, bool $markdown): ?array {
    $char = $text[$index];

    if ($markdown && $char === '`' && preg_match('/\G`([^`]+)`/', $text, $matches, 0, $index) === 1) {
      return [new MarkupSegment(MarkupKind::Code, $matches[1]), strlen($matches[0])];
    }

    if ($char === '[' && preg_match('/\G\[([^\]]*)\]\(([^)]+)\)/', $text, $matches, 0, $index) === 1 && self::looksLikeUrl($matches[2])) {
      // Control bytes in a link would break the escape wrapper it renders to.
      $label = Ansi::stripControl($matches[1]);
      $url = Ansi::stripControl($matches[2]);

      return [new MarkupSegment(MarkupKind::Link, $label, $url), strlen($matches[0])];
    }

    if ($markdown && $char === '*' && preg_match('/\G\*\*(\S(?:.*?\S)?)\*\*/', $text, $matches, 0, $index) === 1) {
      return [new MarkupSegment(MarkupKind::Bold, $matches[1]), strlen($matches[0])];
    }

    if ($markdown && $char === '*' && preg_match('/\G\*([^\s*](?:[^*]*[^\s*])?)\*/', $text, $matches, 0, $index) === 1) {
      return [new MarkupSegment(MarkupKind::Emphasis, $matches[1]), strlen($matches[0])];
    }

    return NULL;
  }

  /**
   * Whether a link target is a real URL, not incidental bracket-paren text.
   *
   * Requiring a scheme keeps a literal `[note](see step 3)` from becoming a
   * dead link; only an addressable target a terminal could open is linked.
   *
   * @param string $url
   *   The candidate target.
   *
   * @return bool
   *   TRUE when the target carries a URL scheme.
   */
  protected static function looksLikeUrl(string $url): bool {
    return preg_match('#^(?:[a-z][a-z0-9+.\-]*://|mailto:|tel:)#i', $url) === 1;
  }

}
