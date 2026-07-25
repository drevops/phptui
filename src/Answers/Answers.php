<?php

declare(strict_types=1);

namespace DrevOps\Tui\Answers;

use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Panel;
use DrevOps\Tui\Render\Terminal;

/**
 * The collected answer set: values plus provenance, keyed by question id.
 *
 * This is the stable contract callers and handlers depend on - the question
 * ids and their value types. Only active (conditional-passing) questions are
 * present. Provenance is one of default / detected / edited / derived /
 * override.
 *
 * Answer sets produced by the engine and the panel TUI are self-describing:
 * each answer carries a snapshot of its question (label, kind, panel trail)
 * in items(), so summaries and processing need no form configuration.
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
   * Build a self-describing answer set from a form definition.
   *
   * Walks the panel tree in form order and snapshots each active question
   * (label, kind, panel trail) into its answer.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The form definition the answers were collected against.
   * @param array<string,mixed> $values
   *   The answer values keyed by question id.
   * @param array<string,\DrevOps\Tui\Answers\Provenance> $provenance
   *   The provenance keyed by question id.
   *
   * @return self
   *   The answer set.
   */
  public static function forForm(FormDefinition $form, array $values, array $provenance): self {
    $items = [];

    foreach ($form->panels as $panel) {
      $items = [...$items, ...self::walkPanel($panel, [], $values, $provenance)];
    }

    return new self($values, $provenance, $items);
  }

  /**
   * Walk a panel and its sub-panels, snapshotting each active field.
   *
   * @param \DrevOps\Tui\Model\Panel $panel
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
  protected static function walkPanel(Panel $panel, array $trail, array $values, array $provenance): array {
    $trail[] = $panel->title;

    $items = [];
    foreach ($panel->fields as $field) {
      if (!array_key_exists($field->id, $values)) {
        continue;
      }

      $items[$field->id] = new Answer($field->id, $values[$field->id], $provenance[$field->id] ?? Provenance::Default, $field->label, $field->type, $trail);
    }

    foreach ($panel->panels as $subpanel) {
      $items = [...$items, ...self::walkPanel($subpanel, $trail, $values, $provenance)];
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
   * a capable terminal, and to `text (url)` otherwise.
   *
   * @param bool|null $hyperlinks
   *   Whether to emit clickable hyperlinks; NULL honours the terminal's colour
   *   capability (the NO_COLOR convention and a "dumb" terminal turn it off).
   *
   * @return string
   *   The formatted summary (empty when the set carries no snapshots).
   */
  public function toSummary(?bool $hyperlinks = NULL): string {
    return (new SummaryFormatter($hyperlinks ?? Terminal::detectColor()))->format($this);
  }

}
