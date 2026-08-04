<?php

declare(strict_types=1);

namespace DrevOps\Tui\Primitive\Element;

use DrevOps\Tui\Primitive\Status;

/**
 * The pieces a primitive draws through.
 *
 * A primitive collects nothing and never runs inside a panel, so it cannot ask
 * a block for anything: what it draws is a whole finished piece - a card, a
 * grid, a status line - rather than one styled string. Every method here takes
 * plain strings and arrays and nothing else, which is exactly what lets the
 * same piece be drawn standalone and from inside a form: a renderer that
 * reached for a field, a panel or an answer could only ever be used from inside
 * one.
 *
 * {@see renderCard()} is the single renderer behind both the standalone card
 * and the one a markup block draws in a panel, grid included, so overriding it
 * restyles the two together. {@see renderTable()} draws the standalone grid.
 *
 * The prefix follows the rule the block interfaces do: every method is named
 * for what it draws, so a theme implements this and every element interface on
 * one class without collisions.
 *
 * @package DrevOps\Tui\Primitive\Element
 */
interface PrimitiveElementsInterface {

  /**
   * Draw a card: a heading, a body and an optional grid, boxed or indented.
   *
   * @param string $title
   *   The heading shown as the card's first line; empty for a bare card.
   * @param list<string> $body
   *   The body lines. Each is word-wrapped to the card's inner width, and an
   *   empty entry stays an empty line so a caller can space the content out.
   * @param list<string> $headers
   *   The header cells of an optional grid below the body.
   * @param list<list<string>> $rows
   *   The body rows of that grid; with no headers or rows there is no grid.
   * @param bool $bordered
   *   Whether the card is boxed in the theme's border, or merely indented.
   * @param int $reserved
   *   Columns the caller lays the card out after, kept out of the width cap so
   *   the card's right edge still lands inside the frame.
   *
   * @return list<string>
   *   The card's physical lines; empty when it has no content at all.
   */
  public function renderCard(string $title, array $body, array $headers = [], array $rows = [], bool $bordered = TRUE, int $reserved = 0): array;

  /**
   * Draw an aligned, bordered grid from headers and rows.
   *
   * The columns size to their widest cell and the whole grid is capped at the
   * frame width.
   *
   * @param list<string> $headers
   *   The header cells; an empty list draws the grid with no header row.
   * @param list<list<string>> $rows
   *   The body rows, each a list of cell strings.
   *
   * @return list<string>
   *   The grid's physical lines, capped at the frame width.
   */
  public function renderTable(array $headers, array $rows): array;

  /**
   * Draw source text as wrapped, markup-styled lines at the frame width.
   *
   * @param string $text
   *   The source text; its own newlines split it into physical lines first.
   *
   * @return list<string>
   *   The styled lines.
   */
  public function renderText(string $text): array;

  /**
   * Draw a line spanning the frame, telling what is above it from what is not.
   *
   * @return string
   *   The styled rule.
   */
  public function renderRule(): string;

  /**
   * Draw a start banner: the logo above an optional version line.
   *
   * @param string $logo
   *   The banner logo; its newlines split it into lines.
   * @param string $version
   *   The version shown below the logo, or an empty string for none.
   *
   * @return string
   *   The composed banner.
   */
  public function renderBanner(string $logo, string $version): string;

  /**
   * Draw a status line: the kind's glyph and the message, in its colour.
   *
   * @param \DrevOps\Tui\Primitive\Status $status
   *   The kind of status.
   * @param string $text
   *   The message; its line breaks fold to spaces so the status stays one line.
   *
   * @return string
   *   The composed line.
   */
  public function renderStatus(Status $status, string $text): string;

  /**
   * Draw label/value pairs as an aligned definition list.
   *
   * @param array<array-key,string> $pairs
   *   The values keyed by their label. A numeric-string label arrives as an
   *   integer key and still renders as its own text.
   *
   * @return list<string>
   *   The list's physical lines; empty when there are no pairs.
   */
  public function renderDefinitions(array $pairs): array;

  /**
   * Draw an indeterminate spinner: an accent glyph before the caption.
   *
   * @param int $frame
   *   The animation frame counter; the glyph cycles through the frame set.
   * @param string $caption
   *   The caption shown beside the spinner.
   *
   * @return string
   *   The composed spinner line.
   */
  public function renderSpinner(int $frame, string $caption): string;

  /**
   * Draw a determinate bar: a filling track, a step count and a label.
   *
   * @param int $current
   *   The number of completed steps.
   * @param int $total
   *   The total number of steps; a zero total renders a full bar.
   * @param string $caption
   *   The caption shown before the bar.
   * @param string $label
   *   The trailing label, or an empty string for none.
   *
   * @return string
   *   The composed bar line.
   */
  public function renderProgressBar(int $current, int $total, string $caption, string $label): string;

}
