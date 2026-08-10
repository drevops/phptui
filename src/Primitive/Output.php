<?php

declare(strict_types=1);

namespace DrevOps\Tui\Primitive;

use DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface;
use DrevOps\Tui\Terminal\Ansi;
use DrevOps\Tui\Terminal\Terminal;

/**
 * Static output primitives: a box, a status line, a definition list.
 *
 * The chrome that surrounds a form rather than collecting anything - a welcome
 * box before it, a summary and its next steps after it, and the status lines
 * between the steps. The active theme draws every piece, so standalone output
 * matches the panel's look and honours its colour and Unicode modes, and the
 * lines wrap to the same frame width.
 *
 * Unlike {@see Progress} there is nothing to animate: each call writes finished
 * lines and returns, so the output is identical on a terminal and in a captured
 * log except for the colour a non-interactive stream drops.
 *
 * @code
 * $out = $tui->output();
 * $out->box('Everything below is optional.', 'Welcome');
 * $out->success('Preserves are ready');
 * $out->definitions(['Jars' => '12', 'Fruit' => 'Apricot']);
 * @endcode
 *
 * @package DrevOps\Tui\Primitive
 */
final class Output {

  /**
   * Construct an output primitive.
   *
   * @param \DrevOps\Tui\Terminal\Terminal $terminal
   *   The terminal the lines are written to.
   * @param \DrevOps\Tui\Primitive\Element\PrimitiveElementsInterface $theme
   *   The theme that draws the box, the status glyphs and the list.
   */
  public function __construct(protected Terminal $terminal, protected PrimitiveElementsInterface $theme) {
  }

  /**
   * Write a bordered box with an optional title.
   *
   * @param string|list<string> $body
   *   The body: one string (its newlines splitting it into lines), or a list of
   *   lines. Long lines wrap to the box's inner width, and an empty entry of a
   *   list stays a blank line so the content can be spaced out.
   * @param string $title
   *   The heading shown as the box's first line; empty for a bare box.
   *
   * @return $this
   *   The primitive.
   */
  public function box(string|array $body, string $title = ''): self {
    return $this->writeLines($this->theme->renderCard(Ansi::sanitize($title), self::toLines($body)));
  }

  /**
   * Write a card: a box with a grid below its body.
   *
   * @param string $title
   *   The heading shown as the card's first line; empty for a bare card.
   * @param string|list<string> $body
   *   The body, as one string or a list of lines; empty for a grid alone.
   * @param list<string> $headers
   *   The grid's header cells; an empty list draws it with no header row.
   * @param list<list<string>> $rows
   *   The grid's body rows.
   * @param bool $bordered
   *   Whether the card is boxed in the theme's border, or merely indented.
   *
   * @return $this
   *   The primitive.
   */
  public function card(string $title, string|array $body = '', array $headers = [], array $rows = [], bool $bordered = TRUE): self {
    return $this->writeLines($this->theme->renderCard(Ansi::sanitize($title), self::toLines($body), self::toCells($headers), array_map(self::toCells(...), $rows), $bordered));
  }

  /**
   * Write an aligned, bordered table.
   *
   * @param list<string> $headers
   *   The header cells; an empty list draws the grid with no header row.
   * @param list<list<string>> $rows
   *   The body rows, each a list of cell strings.
   *
   * @return $this
   *   The primitive.
   */
  public function table(array $headers, array $rows): self {
    return $this->writeLines($this->theme->renderTable(self::toCells($headers), array_map(self::toCells(...), $rows)));
  }

  /**
   * Write a paragraph, wrapped to the terminal and carrying the markup subset.
   *
   * @param string $text
   *   The text; its own newlines split it into physical lines first.
   *
   * @return $this
   *   The primitive.
   */
  public function text(string $text): self {
    return $this->writeLines($this->theme->renderText(Ansi::sanitize($text)));
  }

  /**
   * Write a horizontal rule spanning the frame.
   *
   * @return $this
   *   The primitive.
   */
  public function rule(): self {
    return $this->writeLines([$this->theme->renderRule()]);
  }

  /**
   * Write a start banner: the logo above an optional version line.
   *
   * @param string $logo
   *   The logo; its newlines split it into lines.
   * @param string $version
   *   The version shown below the logo, or an empty string for none.
   *
   * @return $this
   *   The primitive.
   */
  public function banner(string $logo, string $version = ''): self {
    return $this->writeLines(explode("\n", $this->theme->renderBanner(Ansi::sanitize($logo), Ansi::sanitize($version))));
  }

  /**
   * Write a status line of the given kind.
   *
   * @param \DrevOps\Tui\Primitive\Status $status
   *   The kind of status.
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function status(Status $status, string $text): self {
    return $this->writeLines([$this->theme->renderStatus($status, Ansi::sanitize($text))]);
  }

  /**
   * Write a note line: an aside, quieter than the output around it.
   *
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function note(string $text): self {
    return $this->status(Status::Note, $text);
  }

  /**
   * Write an info line: a neutral statement of what is happening.
   *
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function info(string $text): self {
    return $this->status(Status::Info, $text);
  }

  /**
   * Write a success line: something finished as intended.
   *
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function success(string $text): self {
    return $this->status(Status::Success, $text);
  }

  /**
   * Write a warning line: something needs attention but did not stop the run.
   *
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function warning(string $text): self {
    return $this->status(Status::Warning, $text);
  }

  /**
   * Write an error line: something failed.
   *
   * @param string $text
   *   The message.
   *
   * @return $this
   *   The primitive.
   */
  public function error(string $text): self {
    return $this->status(Status::Error, $text);
  }

  /**
   * Write label/value pairs as an aligned definition list.
   *
   * @param array<array-key,string> $pairs
   *   The values keyed by their label. A numeric-string label arrives as an
   *   integer key and still renders as its own text.
   *
   * @return $this
   *   The primitive.
   */
  public function definitions(array $pairs): self {
    $sanitized = [];

    foreach ($pairs as $label => $value) {
      $sanitized[is_string($label) ? Ansi::sanitize($label) : $label] = Ansi::sanitize($value);
    }

    return $this->writeLines($this->theme->renderDefinitions($sanitized));
  }

  /**
   * Normalize a body argument to a list of lines.
   *
   * @param string|list<string> $body
   *   The body, as one string or a list of lines.
   *
   * @return list<string>
   *   The lines.
   */
  protected static function toLines(string|array $body): array {
    // A single empty string is an absent body, not a blank line: a caller
    // spacing content out passes a list, and box('') must draw nothing.
    $lines = is_array($body) ? $body : ($body === '' ? [] : [$body]);

    return array_map(Ansi::sanitize(...), $lines);
  }

  /**
   * Filter a row of grid cells.
   *
   * @param list<string> $cells
   *   The cells.
   *
   * @return list<string>
   *   The cells, each carrying nothing a terminal acts on.
   */
  protected static function toCells(array $cells): array {
    return array_map(Ansi::sanitize(...), $cells);
  }

  /**
   * Write rendered lines, each on its own row.
   *
   * @param list<string> $lines
   *   The rendered lines.
   *
   * @return $this
   *   The primitive.
   */
  protected function writeLines(array $lines): self {
    if ($lines === []) {
      return $this;
    }

    // Flush so the output reaches a block-buffered stream in step with the work
    // around it, rather than arriving in a lump when the process exits.
    $this->terminal->write(implode("\n", $lines) . "\n");
    $this->terminal->flush();

    return $this;
  }

}
