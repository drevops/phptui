<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Resolver\EnvNameResolver;

/**
 * Generates a machine-readable schema of every configured question.
 *
 * Each prompt entry carries `{id, type, label, description, help, placeholder,
 * options, default, required}` plus the declared bounds, the environment
 * variables that answer it and the `when`, `derive` and `discover` rules, so
 * external tooling can drive or validate the form without loading the PHP
 * declaration. A closure default is resolved against the context (see
 * {@see DefaultResolver}) so a computed default advertises a real value, and
 * options that follow the answers resolve against the same context (see
 * {@see OptionsResolver}) and are flagged as `options_dynamic`, so tooling can
 * tell a field with no options from one whose options are not fixed.
 *
 * @package DrevOps\Tui\Schema
 */
class SchemaGenerator {

  /**
   * Construct a generator.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The declared tree to describe, read from the panel every declared panel
   *   hangs from.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context a closure default is evaluated against; defaults to an empty
   *   context carrying no prior answers.
   * @param string $envPrefix
   *   The prefix for per-question env variable names (e.g. "APP_"); under an
   *   empty prefix only a field naming its own variable advertises one.
   */
  public function __construct(protected Panel $root, protected Context $context = new Context(), protected string $envPrefix = '') {
  }

  /**
   * Generate the schema.
   *
   * @return array<string,mixed>
   *   The schema, keyed by `prompts`.
   */
  public function generate(): array {
    $names = new EnvNameResolver($this->envPrefix);
    $prompts = [];

    foreach (Tree::fields($this->root) as $field) {
      // A display-only field (a note or a progress row) collects no answer, so
      // it is not a prompt external tooling drives or validates.
      if ($field->type()->isDisplayOnly()) {
        continue;
      }

      $rule = $field->rule();
      $bounds = $field->numberBounds();
      $dates = $field->dateBounds();
      $discover = $field->discovery();

      $prompts[] = [
        'id' => $field->id(),
        'type' => $field->type()->value,
        'label' => $field->label(),
        'description' => $field->descriptionText(),
        'help' => $field->helpText(),
        'placeholder' => $field->placeholderText(),
        'options' => $this->options($field),
        'options_dynamic' => $field->hasDynamicEntries(),
        'default' => DefaultResolver::resolve($field, $this->context),
        'required' => $field->isRequired(),
        'env' => $names->isAdvertisable($field) ? $names->canonical($field) : NULL,
        'env_aliases' => $names->aliases($field),
        'min' => $bounds?->min,
        'max' => $bounds?->max,
        'step' => $bounds?->step,
        'min_selections' => $field->selectionBounds()?->min,
        'max_selections' => $field->selectionBounds()?->max,
        'min_date' => $dates?->min?->format('Y-m-d'),
        'max_date' => $dates?->max?->format('Y-m-d'),
        'week_start' => $dates?->weekStart->value,
        'template' => $field->template()?->pattern(),
        'placeholders' => $field->template()?->placeholders() ?? [],
        'when' => $rule?->toArray(),
        'derive' => $field->derivation()?->toArray(),
        'discover' => $discover instanceof DiscoverInterface ? $discover->toArray() : NULL,
        'depends_on' => $rule === NULL ? [] : $rule->fields(),
      ];
    }

    return ['prompts' => $prompts];
  }

  /**
   * Describe a field's options.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return array<int,array<string,string>>
   *   The selectable options as a list of {value, label, description};
   *   separators, headings and disabled options are excluded.
   */
  protected function options(Field $field): array {
    OptionsResolver::resolve($field, $this->context);

    $out = [];

    foreach ($field->entries() as $option) {
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
