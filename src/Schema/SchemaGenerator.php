<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Discovery\DiscoverInterface;

/**
 * Generates a machine-readable schema of every configured question.
 *
 * Each prompt entry carries `{id, type, label, description, options, default,
 * required}` plus the declared bounds and the `when`, `derive` and `discover`
 * rules, so external tooling can drive or validate the form without loading
 * the PHP declaration. A closure default is resolved against the context (see
 * {@see DefaultResolver}) so a computed default advertises a real value.
 *
 * @package DrevOps\Tui\Schema
 */
class SchemaGenerator {

  /**
   * Construct a generator.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The configuration to describe.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context a closure default is evaluated against; defaults to an empty
   *   context carrying no prior answers.
   */
  public function __construct(protected FormDefinition $form, protected Context $context = new Context()) {
  }

  /**
   * Generate the schema.
   *
   * @return array<string,mixed>
   *   The schema, keyed by `prompts`.
   */
  public function generate(): array {
    $prompts = [];

    foreach ($this->form->fields() as $field) {
      // A display-only field (a note or a progress row) collects no answer, so
      // it is not a prompt external tooling drives or validates.
      if ($field->type->isDisplayOnly()) {
        continue;
      }

      $prompts[] = [
        'id' => $field->id,
        'type' => $field->type->value,
        'label' => $field->label,
        'description' => $field->description,
        'options' => $this->options($field),
        'default' => DefaultResolver::resolve($field, $this->context),
        'required' => $field->required,
        'min' => $field->bounds?->min,
        'max' => $field->bounds?->max,
        'step' => $field->bounds?->step,
        'min_selections' => $field->selectionBounds?->min,
        'max_selections' => $field->selectionBounds?->max,
        'min_date' => $field->dateBounds?->min?->format('Y-m-d'),
        'max_date' => $field->dateBounds?->max?->format('Y-m-d'),
        'week_start' => $field->dateBounds?->weekStart->value,
        'when' => $field->when?->toArray(),
        'derive' => $field->derive?->toArray(),
        'discover' => $field->discover instanceof DiscoverInterface ? $field->discover->toArray() : NULL,
        'depends_on' => $field->when === NULL ? [] : $field->when->fields(),
      ];
    }

    return ['prompts' => $prompts];
  }

  /**
   * Describe a field's options.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<int,array<string,string>>
   *   The selectable options as a list of {value, label, description};
   *   separators, headings and disabled options are excluded.
   */
  protected function options(Field $field): array {
    $out = [];

    foreach ($field->options as $option) {
      if (!$option->selectable()) {
        continue;
      }

      $out[] = [
        'value' => $option->value,
        'label' => $option->label,
        'description' => $option->description,
      ];
    }

    return $out;
  }

}
