<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Translation\Translator;

/**
 * Describes how to answer the form unattended as a JSON Schema.
 *
 * The schema (draft 2020-12) types the answers object keyed by question id:
 * each property carries its allowed values (a `select`'s options, a number's
 * bounds), its `title`/`description`, whether it is `required`, its `default`,
 * and the `env` variable that sets it. Only what the library controls appears
 * here - the CLI flags an agent ultimately calls are the consumer's to define,
 * so they are absent. The resolution order is the root `x-precedence`.
 *
 * @package DrevOps\Tui\Schema
 */
class AgentHelp {

  /**
   * Construct the schema generator.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The form definition to describe.
   * @param string $envPrefix
   *   The prefix for per-question env variable names (e.g. "APP_"); an empty
   *   prefix omits the `env` annotation.
   */
  public function __construct(protected FormDefinition $form, protected string $envPrefix = '') {
  }

  /**
   * Generate the answer schema.
   *
   * @return string
   *   The JSON Schema, pretty-printed.
   */
  public function generate(): string {
    $properties = [];
    $required = [];

    foreach ($this->form->fields() as $field) {
      // A pause or a progress row is chrome, not a question, so it has no
      // answer.
      if ($field->type === FieldType::Pause || $field->type->isDisplayOnly()) {
        continue;
      }

      $properties[$field->id] = $this->property($field);

      if ($field->required) {
        $required[] = $field->id;
      }
    }

    $schema = [
      '$schema' => 'https://json-schema.org/draft/2020-12/schema',
      'type' => 'object',
      'properties' => $properties,
    ];

    if ($required !== []) {
      $schema['required'] = $required;
    }

    $schema['x-precedence'] = ['provided', 'environment', 'discovered', 'derived', 'default'];

    return (string) json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Build the schema property for one field.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return array<string,mixed>
   *   The property definition.
   */
  protected function property(Field $field): array {
    $values = $this->optionValues($field);
    $property = [];

    if ($field->collectsList()) {
      $property['type'] = 'array';
      $property['items'] = $values === [] ? ['type' => 'string'] : ['enum' => $values];
    }
    elseif ($field->type === FieldType::Number) {
      $property['type'] = 'integer';
    }
    elseif ($field->type === FieldType::Confirm) {
      $property['type'] = 'boolean';
    }
    else {
      $property['type'] = 'string';

      if ($values !== []) {
        $property['enum'] = $values;
      }
    }

    if ($field->type === FieldType::Calendar) {
      $property['format'] = 'date';
    }

    // The step is a keyboard increment, not a value constraint - the library
    // accepts any in-range integer - so it never becomes a `multipleOf` that
    // would reject values the collection allows.
    if ($field->bounds instanceof NumberBounds) {
      if ($field->bounds->min !== NULL) {
        $property['minimum'] = $field->bounds->min;
      }
      if ($field->bounds->max !== NULL) {
        $property['maximum'] = $field->bounds->max;
      }
    }

    if ($field->label !== '') {
      $property['title'] = Translator::t($field->label);
    }

    if ($field->description !== '') {
      $property['description'] = Translator::t($field->description);
    }

    if (!$field->default instanceof \Closure && $field->default !== NULL && $field->default !== '' && $field->default !== []) {
      $property['default'] = $field->default;
    }

    if ($this->envPrefix !== '') {
      $property['env'] = $this->envPrefix . strtoupper($field->id);
    }

    return $property;
  }

  /**
   * The selectable option values of an option-constrained field.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   *
   * @return list<string>
   *   The values a supplied answer must be one of, or an empty list when the
   *   field is not constrained to a closed set.
   */
  protected function optionValues(Field $field): array {
    return $field->type->constrainsToOptions() ? $field->selectableValues() : [];
  }

}
