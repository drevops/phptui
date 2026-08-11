<?php

declare(strict_types=1);

namespace DrevOps\Tui\Answers;

use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Terminal\Terminal;

/**
 * The collected answer set: values plus provenance, keyed by question id.
 *
 * This is the stable contract callers and handlers depend on - the question
 * ids and their value types. Only active (conditional-passing) questions are
 * present. Provenance is one of default / detected / edited / derived /
 * override.
 *
 * An answer set is self-describing: each answer carries a snapshot of its
 * question (label, kind, panel trail) in items(), so summaries and processing
 * need no form configuration.
 *
 * @package DrevOps\Tui\Answers
 */
final readonly class Answers {

  /**
   * Construct an answer set.
   *
   * @param array<string,mixed> $values
   *   The answer values keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The provenance keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Answer> $items
   *   The self-describing answers keyed by question id, in form order (empty
   *   when the set was assembled without a configuration).
   */
  public function __construct(
    public array $values = [],
    public array $provenance = [],
    public array $items = [],
  ) {
  }

  /**
   * Build a self-describing answer set from a declared block tree.
   *
   * The tree's root is the form itself rather than a declared panel, so it
   * contributes no heading: the trail each answer carries starts at the panel
   * it was asked in.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The panel every declared panel hangs from.
   * @param array<string,mixed> $values
   *   The answer values keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The provenance keyed by question id.
   *
   * @return self
   *   The answer set.
   */
  public static function forTree(Panel $root, array $values, array $provenance): self {
    $items = self::snapshot($root, [], $values, $provenance);

    foreach ($root->children() as $panel) {
      $items = [...$items, ...self::walkTree($panel, [], $values, $provenance)];
    }

    return new self($values, $provenance, $items);
  }

  /**
   * Walk a panel block and the panels beneath it, snapshotting each answer.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel to walk.
   * @param list<string> $trail
   *   The titles of the ancestor panels, outermost first.
   * @param array<string,mixed> $values
   *   The answer values keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The provenance keyed by question id.
   *
   * @return array<string,\DrevOps\Tui\Answers\Answer>
   *   The self-describing answers keyed by question id.
   */
  protected static function walkTree(Panel $panel, array $trail, array $values, array $provenance): array {
    $trail[] = $panel->title();

    $items = self::snapshot($panel, $trail, $values, $provenance);

    foreach ($panel->children() as $child) {
      $items = [...$items, ...self::walkTree($child, $trail, $values, $provenance)];
    }

    return $items;
  }

  /**
   * Snapshot the questions one panel asked, in the order it asked them.
   *
   * @param \DrevOps\Tui\Block\Panel $panel
   *   The panel.
   * @param list<string> $trail
   *   The titles of the panels the questions live under, outermost first.
   * @param array<string,mixed> $values
   *   The answer values keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The provenance keyed by question id.
   *
   * @return array<string,\DrevOps\Tui\Answers\Answer>
   *   The self-describing answers keyed by question id.
   */
  protected static function snapshot(Panel $panel, array $trail, array $values, array $provenance): array {
    $items = [];

    foreach ($panel->fields() as $field) {
      if (!array_key_exists($field->id(), $values)) {
        continue;
      }

      $items[$field->id()] = new Answer($field->id(), $values[$field->id()], $provenance[$field->id()] ?? Provenance::Default, $field->label(), $field->type(), $trail, $field->templateParts($values[$field->id()]));
    }

    return $items;
  }

  /**
   * Whether the set contains a question.
   *
   * @param string $id
   *   The question id.
   *
   * @return bool
   *   TRUE when present.
   */
  public function has(string $id): bool {
    return array_key_exists($id, $this->values);
  }

  /**
   * The value for a question.
   *
   * @param string $id
   *   The question id.
   *
   * @return mixed
   *   The value, or NULL when absent.
   */
  public function value(string $id): mixed {
    return $this->values[$id] ?? NULL;
  }

  /**
   * The slot values of a template question's answer.
   *
   * The answer itself stays the assembled string; the parts are read back from
   * it, so a caller can take the whole value, the pieces, or both.
   *
   * @param string $id
   *   The question id.
   *
   * @return array<string,string>
   *   The value of each slot keyed by slot name; empty when the question is
   *   absent, is not a template, or its answer does not have the shape.
   */
  public function parts(string $id): array {
    return $this->items[$id]->parts ?? [];
  }

  /**
   * The provenance for a question.
   *
   * @param string $id
   *   The question id.
   *
   * @return \DrevOps\Tui\Answers\Provenance
   *   The provenance (Default when absent).
   */
  public function provenanceOf(string $id): Provenance {
    return $this->provenance[$id] ?? Provenance::Default;
  }

  /**
   * The self-describing answer for a question.
   *
   * @param string $id
   *   The question id.
   *
   * @return \DrevOps\Tui\Answers\Answer|null
   *   The answer, or NULL when absent (or the set carries no snapshots).
   */
  public function item(string $id): ?Answer {
    return $this->items[$id] ?? NULL;
  }

  /**
   * The answer values as a JSON string.
   *
   * @return string
   *   The JSON-encoded values.
   */
  public function toJson(): string {
    return (string) json_encode($this->values);
  }

  /**
   * The answers as a human summary grouped by panel.
   *
   * A linked label in a question or panel resolves to a clickable hyperlink on
   * a capable terminal, and to `text (url)` otherwise. Capability follows the
   * terminal's colour support (the NO_COLOR convention and a "dumb" terminal
   * turn it off), the same signal the interactive TUI uses.
   *
   * @return string
   *   The formatted summary (empty when the set carries no snapshots).
   */
  public function toSummary(): string {
    return (new SummaryFormatter(Terminal::detectColor()))->format($this);
  }

}
