<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Translation\Translator;

/**
 * Describes how to answer the form unattended as a JSON Schema.
 *
 * The schema (draft 2020-12) types the answers object keyed by question id:
 * each property carries its allowed values (a `select`'s options, a number's
 * bounds), its `title`/`description`, whether it is `required`, its `default`,
 * and the `env` variable that sets it. Only what the library controls appears
 * here - the CLI flags an agent ultimately calls are the consumer's to define,
 * so they are absent. The resolution order is the root `x-precedence`. A
 * closure default is resolved against the context (see {@see DefaultResolver})
 * rather than omitted.
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
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context a closure default is evaluated against; defaults to an empty
   *   context carrying no prior answers.
   */
  public function __construct(protected FormDefinition $form, protected string $envPrefix = '', protected Context $context = new Context()) {
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
      // A pause is a gate, and a note or a progress row is display-only: none
      // is a question, so none carries an answer.
      if ($field->type === FieldType::Pause) {
        continue;
      }
      if ($field->type->isDisplayOnly()) {
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

    // A template answer is the assembled string, so its shape travels as the
    // expression that string must match rather than as the pattern's own
    // `{{slot}}` syntax, which no schema consumer would understand.
    if ($field->template instanceof Template) {
      $property['pattern'] = $field->template->schemaPattern();
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

    if ($field->selectionBounds instanceof SelectionBounds) {
      if ($field->selectionBounds->min !== NULL) {
        $property['minItems'] = $field->selectionBounds->min;
      }
      if ($field->selectionBounds->max !== NULL) {
        $property['maxItems'] = $field->selectionBounds->max;
      }
    }

    if ($field->label !== '') {
      $property['title'] = Translator::t($field->label);
    }

    if ($field->description !== '') {
      $property['description'] = Translator::t($field->description);
    }

    $default = DefaultResolver::resolve($field, $this->context);
    if (!in_array($default, [NULL, '', []], TRUE)) {
      $property['default'] = $default;
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
