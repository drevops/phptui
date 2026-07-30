<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Capability\DependCapableInterface;
use DrevOps\Tui\Block\Capability\DependCapableTrait;
use DrevOps\Tui\Block\Element\MarkupElementsInterface;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Formatted content, and nothing else.
 *
 * How it is laid out on the page - prose, a bordered card, a table - is a
 * presentation choice rather than a capability, so all three are this one block
 * drawn three ways. What an earlier answer can change is whether it is there at
 * all, which is why a standing warning needs no block of its own.
 *
 * @package DrevOps\Tui\Block
 */
final class Markup extends AbstractBlock implements DependCapableInterface {

  use DependCapableTrait;

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
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, MarkupElementsInterface::class, 'markup');
    $lines = [];

    if ($this->title !== '') {
      $lines[] = $elements->markupTitle($this->title);
    }

    // A Windows-authored body carries CRLF endings, and a surviving carriage
    // return would send the cursor back to column 0 and overprint the row.
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $this->body)) as $line) {
      $lines[] = $elements->markupLine($line);
    }

    return implode("\n", $lines);
  }

}
