<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Element\MarkupElementsInterface;
use DrevOps\Tui\Model\TableSpec;
use DrevOps\Tui\Theme\ThemeInterface;
use DrevOps\Tui\Translation\Translator;

/**
 * Formatted content, and nothing else.
 *
 * How it is laid out on the page - prose, a bordered card, a table - is a
 * presentation choice rather than a capability, so all three are this one block
 * drawn three ways. What an earlier answer can change is whether it is there at
 * all, which is why a standing warning needs no block of its own.
 *
 * Prose is composed line by line from the theme's markup elements. A card and a
 * grid are laid out rather than styled - they measure their content, wrap it
 * and align it - so both go to the theme's card renderer, the one that already
 * draws a card wherever else the library draws one.
 *
 * @package DrevOps\Tui\Block
 */
final class Markup extends AbstractBlock implements DependCapableInterface {

  use DependCapableTrait;

  /**
   * Whether it is drawn inside a border rather than as bare lines.
   */
  protected bool $bordered = FALSE;

  /**
   * The grid drawn beneath the body, or NULL when it carries none.
   */
  protected ?TableSpec $table = NULL;

  /**
   * Construct a markup block.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param string $body
   *   The content; newlines separate lines.
   * @param string $title
   *   An optional title above the body.
   */
  public function __construct(
    protected string $id,
    protected string $body,
    protected string $title = '',
  ) {
  }

  /**
   * The id this block is addressed by.
   *
   * @return string
   *   The id.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Set the content this block draws.
   *
   * @param string $body
   *   The content; newlines separate lines.
   *
   * @return static
   *   The block.
   */
  public function body(string $body): static {
    $this->body = $body;

    return $this;
  }

  /**
   * The content this block draws.
   *
   * @return string
   *   The body, empty when it carries none.
   */
  public function bodyText(): string {
    return $this->body;
  }

  /**
   * Set the title above this block's body.
   *
   * @param string $title
   *   The title; empty draws the body alone.
   *
   * @return static
   *   The block.
   */
  public function title(string $title): static {
    $this->title = $title;

    return $this;
  }

  /**
   * The title above this block's body.
   *
   * @return string
   *   The title, empty when the body draws alone.
   */
  public function titleText(): string {
    return $this->title;
  }

  /**
   * Draw this block inside a border.
   *
   * @param bool $bordered
   *   Whether it is boxed.
   *
   * @return static
   *   The block.
   */
  public function bordered(bool $bordered = TRUE): static {
    $this->bordered = $bordered;

    return $this;
  }

  /**
   * Whether this block is drawn inside a border.
   *
   * @return bool
   *   TRUE when it is.
   */
  public function isBordered(): bool {
    return $this->bordered;
  }

  /**
   * Lay this block's content out as a grid beneath its body.
   *
   * @param list<mixed> $headers
   *   The header cells; empty draws the grid with no header row.
   * @param list<list<mixed>> $rows
   *   The body rows, each a list of cells.
   *
   * @return static
   *   The block.
   */
  public function table(array $headers, array $rows): static {
    $this->table = new TableSpec($headers, $rows);

    return $this;
  }

  /**
   * The grid drawn beneath this block's body.
   *
   * @return \DrevOps\Tui\Model\TableSpec|null
   *   The grid, or NULL when it carries none.
   */
  public function tableSpec(): ?TableSpec {
    return $this->table;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, MarkupElementsInterface::class, 'markup');

    if ($this->bordered || $this->table instanceof TableSpec) {
      // An empty body is no body at all here: a card that was handed one blank
      // line would spend a row on it, where prose simply draws the blank line.
      $body = $this->body === '' ? [] : $this->lines();
      $headers = $this->table instanceof TableSpec ? $this->table->headers : [];
      $rows = $this->table instanceof TableSpec ? $this->table->rows : [];

      return implode("\n", $theme->renderCard(Translator::t($this->title), $body, $headers, $rows, $this->bordered));
    }

    $lines = [];

    if ($this->title !== '') {
      $lines[] = $elements->markupTitle(Translator::t($this->title));
    }

    foreach (Prose::lines(Translator::t($this->body), $theme, $elements->markupLine(...)) as $line) {
      $lines[] = $line;
    }

    return implode("\n", $lines);
  }

  /**
   * The body as its physical lines.
   *
   * @return list<string>
   *   The lines.
   */
  protected function lines(): array {
    // A Windows-authored body carries CRLF endings, and a surviving carriage
    // return would send the cursor back to column 0 and overprint the row.
    return explode("\n", str_replace(["\r\n", "\r"], "\n", $this->body));
  }

}
