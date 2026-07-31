<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\FieldType;
use DrevOps\Tui\Model\NumberBounds;
use DrevOps\Tui\Model\SelectionBounds;
use DrevOps\Tui\Model\Template;
use DrevOps\Tui\Resolver\EnvNameResolver;
use DrevOps\Tui\Translation\Translator;

/**
 * Describes how to answer the form unattended as a JSON Schema.
 *
 * The schema (draft 2020-12) types the answers object keyed by question id:
 * each property carries its allowed values (a `select`'s options, a number's
 * bounds), its `title`/`description`, whether it is `required`, its `default`,
 * and the `env` variable that sets it - with any further names it answers to
 * in `x-env-aliases`. Only what the library controls appears here - the CLI
 * flags an agent ultimately calls are the consumer's to define, so they are
 * absent. The resolution order is the root `x-precedence`. A closure default is
 * resolved against the context (see {@see DefaultResolver}) rather than
 * omitted.
 *
 * @package DrevOps\Tui\Schema
 */
class AgentHelp {

  /**
   * The variables that answer each field.
   */
  protected EnvNameResolver $names;

  /**
   * Construct the schema generator.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The declared tree to describe, read from the panel every declared panel
   *   hangs from.
   * @param string $envPrefix
   *   The prefix for per-question env variable names (e.g. "APP_"); under an
   *   empty prefix only a field naming its own variable carries the `env`
   *   annotation.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context a closure default is evaluated against; defaults to an empty
   *   context carrying no prior answers.
   */
  public function __construct(protected Panel $root, protected string $envPrefix = '', protected Context $context = new Context()) {
    $this->names = new EnvNameResolver($envPrefix);
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

    foreach (Tree::fields($this->root) as $field) {
      // A pause is a gate, and a note or a progress row is display-only: none
      // is a question, so none carries an answer.
      if ($field->type() === FieldType::Pause) {
        continue;
      }
      if ($field->type()->isDisplayOnly()) {
        continue;
      }
      $properties[$field->id()] = $this->property($field);

      if ($field->isRequired()) {
        $required[] = $field->id();
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
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return array<string,mixed>
   *   The property definition.
   */
  protected function property(Field $field): array {
    $values = $this->optionValues($field);
    $template = $field->template();
    $bounds = $field->numberBounds();
    $selections = $field->selectionBounds();
    $property = [];

    if ($field->collectsList()) {
      $property['type'] = 'array';
      $property['items'] = $values === [] ? ['type' => 'string'] : ['enum' => $values];
    }
    elseif ($field->type()->collectsInteger()) {
      $property['type'] = 'integer';
    }
    elseif ($field->type() === FieldType::Confirm) {
      $property['type'] = 'boolean';
    }
    else {
      $property['type'] = 'string';

      if ($values !== []) {
        $property['enum'] = $values;
      }
    }

    if ($field->type() === FieldType::Calendar) {
      $property['format'] = 'date';
    }

    // A template answer is the assembled string, so its shape travels as the
    // expression that string must match rather than as the pattern's own
    // `{{slot}}` syntax, which no schema consumer would understand.
    if ($template instanceof Template) {
      $property['pattern'] = $template->schemaPattern();
    }

    // The step is a keyboard increment, not a value constraint - the library
    // accepts any in-range integer - so it never becomes a `multipleOf` that
    // would reject values the collection allows.
    if ($bounds instanceof NumberBounds) {
      if ($bounds->min !== NULL) {
        $property['minimum'] = $bounds->min;
      }
      if ($bounds->max !== NULL) {
        $property['maximum'] = $bounds->max;
      }
    }

    if ($selections instanceof SelectionBounds) {
      if ($selections->min !== NULL) {
        $property['minItems'] = $selections->min;
      }
      if ($selections->max !== NULL) {
        $property['maxItems'] = $selections->max;
      }
    }

    if ($field->label() !== '') {
      $property['title'] = Translator::t($field->label());
    }

    if ($field->descriptionText() !== '') {
      $property['description'] = Translator::t($field->descriptionText());
    }

    // Extension keywords: JSON Schema has no slot for either, and folding them
    // into `description` would merge back the three texts a form keeps apart. A
    // placeholder is not `examples` either - it illustrates the shape of an
    // answer without being a valid one.
    if ($field->helpText() !== '') {
      $property['x-help'] = Translator::t($field->helpText());
    }

    if ($field->placeholderText() !== '') {
      $property['x-placeholder'] = Translator::t($field->placeholderText());
    }

    $default = DefaultResolver::resolve($field, $this->context);
    if (!in_array($default, [NULL, '', []], TRUE)) {
      $property['default'] = $default;
    }

    if ($this->names->isAdvertisable($field)) {
      $property['env'] = $this->names->canonical($field);
    }

    // Independent of the canonical name: a declared alias is absolute, so it
    // answers the field even where the mechanical name is too bare to
    // advertise.
    $aliases = $this->names->aliases($field);
    if ($aliases !== []) {
      $property['x-env-aliases'] = $aliases;
    }

    return $property;
  }

  /**
   * The selectable option values of an option-constrained field.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return list<string>
   *   The values a supplied answer must be one of, or an empty list when the
   *   field is not constrained to a closed set.
   */
  protected function optionValues(Field $field): array {
    if (!$field->type()->constrainsToOptions()) {
      return [];
    }

    OptionsResolver::resolve($field, $this->context);

    return $field->selectableValues();
  }

}
