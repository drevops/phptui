<?php

declare(strict_types=1);

namespace DrevOps\Tui\Resolver;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\FieldType;
use DrevOps\Tui\FormException;
use DrevOps\Tui\Translation\Translator;

/**
 * Assembles the non-interactive input map from the external answer layers.
 *
 * The TUI stays dependency-free: this small overlay merges the external
 * layers - per-question environment variables (below) and a `--prompts`
 * JSON string or file (above) - into one input map. That map is the top layer
 * a collection resolves against, so the full precedence becomes
 * `--prompts` > env > discovered > derived > default. Environment values are
 * strings, so they are coerced to the field's type; `--prompts` values are
 * already typed by JSON.
 *
 * Which variables answer a field is {@see EnvNameResolver}'s to say: a field
 * may replace its mechanical name and declare aliases beside it, and the first
 * of those names that is set answers it.
 *
 * @package DrevOps\Tui\Resolver
 */
class InputResolver {

  /**
   * Construct a resolver.
   *
   * @param string $envPrefix
   *   The prefix for per-question env variable names (e.g. "APP_").
   */
  public function __construct(protected string $envPrefix = '') {
  }

  /**
   * Build the input map for the given fields.
   *
   * @param list<\DrevOps\Tui\Block\Field> $fields
   *   The fields to resolve inputs for.
   * @param string $prompts
   *   A `--prompts` JSON string, or a path to a JSON file, or empty.
   * @param array<string,string> $env
   *   The environment map (injected for testability).
   *
   * @return array<string,mixed>
   *   The input map keyed by field id.
   */
  public function resolve(array $fields, string $prompts, array $env): array {
    $names = new EnvNameResolver($this->envPrefix);
    $inputs = [];

    foreach ($fields as $field) {
      foreach ($names->all($field) as $name) {
        if (array_key_exists($name, $env)) {
          $inputs[$field->id()] = $this->coerce($env[$name], $field);

          break;
        }
      }
    }

    foreach ($this->parsePrompts($prompts) as $id => $value) {
      $inputs[$id] = $value;
    }

    return $inputs;
  }

  /**
   * Coerce a string environment value to the field's type.
   *
   * @param string $value
   *   The raw environment value.
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   *
   * @return mixed
   *   The coerced value.
   */
  protected function coerce(string $value, Field $field): mixed {
    $trimmed = trim($value);
    $truthy = ['1', 'true', 'yes', 'on'];

    return match (TRUE) {
      $field->type() === FieldType::Confirm, $field->type() === FieldType::Pause => in_array(strtolower($trimmed), $truthy, TRUE),
      $field->collectsList() => $this->splitList($value),
      // Only an integral value coerces; anything else stays a string so the
      // collection's type check rejects it instead of it silently becoming 0.
      $field->type()->collectsInteger() => preg_match('/^-?\d+$/', $trimmed) === 1 ? (int) $trimmed : $value,
      default => $value,
    };
  }

  /**
   * Split a comma-separated string into a list of trimmed values.
   *
   * @param string $value
   *   The comma-separated value.
   *
   * @return list<string>
   *   The list of values.
   */
  protected function splitList(string $value): array {
    if (trim($value) === '') {
      return [];
    }

    return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn(string $item): bool => $item !== ''));
  }

  /**
   * Parse the `--prompts` operand (JSON string or file) into a map.
   *
   * @param string $prompts
   *   A JSON string, or a path to a JSON file, or empty.
   *
   * @return array<string,mixed>
   *   The decoded map keyed by field id.
   *
   * @throws \DrevOps\Tui\FormException
   *   When the operand decodes to anything but a JSON object - failing loudly
   *   instead of silently discarding every supplied answer.
   */
  protected function parsePrompts(string $prompts): array {
    if ($prompts === '') {
      return [];
    }

    $json = is_file($prompts) ? (string) file_get_contents($prompts) : $prompts;
    $data = json_decode($json, TRUE);

    if (!is_array($data)) {
      throw new FormException(Translator::t('The --prompts value is neither a JSON object nor a path to one.'));
    }

    $out = [];

    foreach ($data as $key => $value) {
      $out[(string) $key] = $value;
    }

    return $out;
  }

}
