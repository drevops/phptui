<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Answers;

use DrevOps\PhpTui\Block\FieldType;
use DrevOps\PhpTui\Terminal\Markup;
use DrevOps\PhpTui\Translation\Translator;

/**
 * Formats a self-describing answer set as a human summary grouped by panel.
 *
 * Panel headings come from each answer's panel trail, so only panels with
 * active answers appear; each value is rendered readably and non-default
 * answers carry a provenance badge.
 *
 * @package DrevOps\PhpTui\Answers
 */
class SummaryFormatter {

  /**
   * Construct a summary formatter.
   *
   * @param bool $hyperlinks
   *   Whether a linked label emits a clickable hyperlink; FALSE degrades each
   *   to `text (url)`.
   */
  public function __construct(protected bool $hyperlinks = FALSE) {
  }

  /**
   * Format the answers grouped by their panel trails.
   *
   * @param \DrevOps\PhpTui\Answers\Answers $answers
   *   The answer set, however it was collected.
   *
   * @return string
   *   The formatted summary.
   */
  public function format(Answers $answers): string {
    $lines = [];
    $trail = [];

    foreach ($answers->items as $item) {
      $lines = array_merge($lines, $this->openPanels($trail, $item->panels));
      $trail = $item->panels;

      $indent = str_repeat('  ', count($item->panels));
      $lines[] = $indent . $this->label($item->label) . ': ' . $this->renderValue($item) . $this->badge($item->provenance);
    }

    return implode("\n", $lines);
  }

  /**
   * The heading lines for the panels an item newly enters.
   *
   * @param list<string> $trail
   *   The previous item's panel trail.
   * @param list<string> $panels
   *   The current item's panel trail.
   *
   * @return list<string>
   *   One indented heading line per panel the trail does not already cover.
   */
  protected function openPanels(array $trail, array $panels): array {
    $common = 0;

    while ($common < count($trail) && isset($panels[$common]) && $trail[$common] === $panels[$common]) {
      $common++;
    }

    $lines = [];

    foreach (array_slice($panels, $common) as $offset => $title) {
      $lines[] = str_repeat('  ', $common + $offset) . $this->label($title);
    }

    return $lines;
  }

  /**
   * Translate a label or panel heading and resolve any links it carries.
   *
   * @param string $source
   *   The label or heading source.
   *
   * @return string
   *   The translated text with links resolved.
   */
  protected function label(string $source): string {
    return Markup::links(Translator::t($source), $this->hyperlinks);
  }

  /**
   * Render an answer's value readably, masking secret values.
   *
   * @param \DrevOps\PhpTui\Answers\Answer $answer
   *   The answer.
   *
   * @return string
   *   The rendered value.
   */
  protected function renderValue(Answer $answer): string {
    $value = $answer->value;

    // Secrets never print: a fixed-length mask hides both value and length.
    if ($answer->type === FieldType::Password) {
      return is_string($value) && $value !== '' ? ValueFormatter::mask('*') : '';
    }

    return ValueFormatter::format($value);
  }

  /**
   * The provenance badge for a value (empty for defaults).
   *
   * @param \DrevOps\PhpTui\Answers\Provenance $provenance
   *   The provenance.
   *
   * @return string
   *   The badge suffix.
   */
  protected function badge(Provenance $provenance): string {
    return $provenance === Provenance::Default ? '' : ' (' . $provenance->label() . ')';
  }

}
