<?php

declare(strict_types=1);

namespace DrevOps\Tui\Answers;

use DrevOps\Tui\Block\FieldType;

/**
 * A single collected answer with a snapshot of the question it answers.
 *
 * The snapshot (label, kind, panel trail) is taken at collection time, so
 * consumers can present or process an answer set without holding the form
 * configuration.
 *
 * @package DrevOps\Tui\Answers
 */
final readonly class Answer {

  /**
   * Construct an answer.
   *
   * @param string $id
   *   The question id.
   * @param mixed $value
   *   The answer value.
   * @param \DrevOps\Tui\Answers\Provenance $provenance
   *   How the value came to be.
   * @param string $label
   *   The question's human-readable label.
   * @param \DrevOps\Tui\Block\FieldType $type
   *   The question kind.
   * @param list<string> $panels
   *   The titles of the panels the question lives under, outermost first.
   * @param array<string,string> $parts
   *   A template question only: the value of each of the shape's slots, keyed
   *   by slot name. Read back from the assembled value, so the two can never
   *   disagree; empty for every other kind of question.
   */
  public function __construct(
    public string $id,
    public mixed $value,
    public Provenance $provenance,
    public string $label,
    public FieldType $type,
    public array $panels,
    public array $parts = [],
  ) {
  }

}
