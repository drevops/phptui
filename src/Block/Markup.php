<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Block\Capability\DependCapableInterface;
use DrevOps\PhpTui\Block\Capability\DependCapableTrait;
use DrevOps\PhpTui\Block\Element\ChromeElementsInterface;
use DrevOps\PhpTui\Block\Element\MarkupElementsInterface;
use DrevOps\PhpTui\Primitive\Element\PrimitiveElementsInterface;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Theme\ThemeInterface;
use DrevOps\PhpTui\Translation\Translator;

/**
 * Formatted content, and nothing else.
 *
 * Prose, a bordered card and a table are one block drawn three ways: the
 * layout is a presentation choice rather than a capability. An earlier
 * answer can change only whether the block is there at all, so a standing
 * warning needs no block of its own.
 *
 * Prose is composed line by line from the theme's markup elements. A card
 * and a grid are laid out rather than styled - they measure their content,
 * wrap it and align it - so both go to the theme's card renderer, the same
 * one that draws a card everywhere else in the library.
 *
 * @package DrevOps\PhpTui\Block
 */
final class Markup extends AbstractBlock implements DependCapableInterface {

  use DependCapableTrait;

  /**
   * The content it draws; newlines separate lines.
   */
  protected string $body;

  /**
   * The title above the body, empty when the body draws alone.
   */
  protected string $title;

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
    string $body,
    string $title = '',
  ) {
    $this->body($body);
    $this->title($title);
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
    $this->body = Ansi::sanitize($body);

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
    $this->title = Ansi::sanitize($title);

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
   * @return \DrevOps\PhpTui\Block\TableSpec|null
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
    $gutter = $this->elements($theme, ChromeElementsInterface::class, 'a conditional row')->chromeIndent($this->depth);

    if ($this->table instanceof TableSpec) {
      // An empty body is no body at all here: a card that was handed one blank
      // line would spend a row on it, where prose simply draws the blank line.
      $body = $this->body === '' ? [] : $this->lines();
      $headers = $this->table->headers;
      $rows = $this->table->rows;

      // The one card renderer, so a bordered note and a standalone box are
      // restyled together rather than drifting apart.
      $pieces = $this->elements($theme, PrimitiveElementsInterface::class, 'a card');

      return $this->stepped(implode("\n", $pieces->renderCard(Translator::t($this->title), $body, $headers, $rows, $this->isBordered())), $gutter);
    }

    $lines = [];

    if ($this->title !== '') {
      $lines[] = $elements->markupTitle(Translator::t($this->title));
    }

    foreach (Prose::lines(Translator::t($this->body), $elements) as $line) {
      $lines[] = $line;
    }

    return $this->stepped(implode("\n", $lines), $gutter);
  }

  /**
   * The body as its physical lines.
   *
   * @return list<string>
   *   The lines.
   */
  protected function lines(): array {
    return explode("\n", $this->body);
  }

}
