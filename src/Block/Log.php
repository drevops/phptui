<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Block;

use DrevOps\PhpTui\Block\Element\MarkupElementsInterface;
use DrevOps\PhpTui\FormException;
use DrevOps\PhpTui\Terminal\Ansi;
use DrevOps\PhpTui\Theme\ThemeInterface;

/**
 * Lines written over time, keeping only the most recent ones.
 *
 * The block holds at most its line limit and drops the oldest lines past it.
 * It draws every line it holds; a region declared with
 * {@see \DrevOps\PhpTui\Screen\Region::follows()} keeps the newest of them in
 * sight.
 *
 * @package DrevOps\PhpTui\Block
 */
final class Log extends AbstractBlock {

  /**
   * The line limit a log takes when none is declared.
   */
  public const int LIMIT = 100;

  /**
   * The lines held, oldest first.
   *
   * @var list<string>
   */
  protected array $lines = [];

  /**
   * The most lines held at once.
   */
  protected int $limit;

  /**
   * Construct a log block.
   *
   * @param string $id
   *   The id it is addressed by.
   * @param int $limit
   *   The most lines it holds at once, at least 1.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the limit is below 1.
   */
  public function __construct(
    protected string $id,
    int $limit = self::LIMIT,
  ) {
    $this->limit($limit);
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
   * Set the most lines this log holds at once.
   *
   * Lowering the limit drops the oldest lines already held past it.
   *
   * @param int $limit
   *   The limit, at least 1.
   *
   * @return static
   *   The block.
   *
   * @throws \DrevOps\PhpTui\FormException
   *   When the limit is below 1.
   */
  public function limit(int $limit): static {
    if ($limit < 1) {
      throw new FormException(sprintf('Log "%s" declares a limit of %d lines; a log holds at least 1 line.', $this->id, $limit));
    }

    $this->limit = $limit;
    $this->trim();

    return $this;
  }

  /**
   * The most lines this log holds at once.
   *
   * @return int
   *   The limit.
   */
  public function lineLimit(): int {
    return $this->limit;
  }

  /**
   * Append lines to the end of this log.
   *
   * Each argument may carry several lines, separated by any line ending.
   *
   * @param string ...$lines
   *   The lines.
   *
   * @return static
   *   The block.
   */
  public function write(string ...$lines): static {
    foreach ($lines as $text) {
      foreach (explode("\n", Ansi::sanitize($text)) as $line) {
        $this->lines[] = $line;
      }
    }

    $this->trim();

    return $this;
  }

  /**
   * Remove every line from this log.
   *
   * @return static
   *   The block.
   */
  public function clear(): static {
    $this->lines = [];

    return $this;
  }

  /**
   * The lines this log holds.
   *
   * @return list<string>
   *   The lines, oldest first.
   */
  public function lines(): array {
    return $this->lines;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ThemeInterface $theme): string {
    $elements = $this->elements($theme, MarkupElementsInterface::class, 'a log');

    return implode("\n", array_map($elements->markupLine(...), $this->lines));
  }

  /**
   * Drop the oldest lines past the limit.
   */
  protected function trim(): void {
    if (count($this->lines) > $this->limit) {
      $this->lines = array_slice($this->lines, -$this->limit);
    }
  }

}
