<?php

declare(strict_types=1);

namespace DrevOps\Tui\Block;

use DrevOps\Tui\Block\Element\MarkupElements;
use DrevOps\Tui\Theme\ThemeInterface;

/**
 * Formatted content, and nothing else.
 *
 * How it is laid out on the page - prose, a bordered card, a table - is a
 * presentation choice rather than a capability, so all three are this one block
 * drawn three ways.
 *
 * @package DrevOps\Tui\Block
 */
final class Markup implements BlockInterface {

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
    if (!$theme instanceof MarkupElements) {
      throw new \InvalidArgumentException(sprintf('%s cannot draw markup: it does not implement %s.', $theme::class, MarkupElements::class));
    }

    $lines = [];

    if ($this->title !== '') {
      $lines[] = $theme->markupTitle($this->title);
    }

    foreach (explode("\n", $this->body) as $line) {
      $lines[] = $theme->markupLine($line);
    }

    return implode("\n", $lines);
  }

}
