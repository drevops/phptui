<?php

declare(strict_types=1);

namespace DrevOps\Tui\Engine;

use DrevOps\Tui\Answers\Answers;
use DrevOps\Tui\Answers\Provenance;
use DrevOps\Tui\Condition\ConditionInterface;
use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\Option;
use DrevOps\Tui\Derive\Deriver;
use DrevOps\Tui\Discovery\DiscoverInterface;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Handler\HandlerRegistry;
use DrevOps\Tui\Translation\Translator;

/**
 * Orchestrates the question lifecycle generically over a form definition.
 *
 * For each configured field the engine resolves a value (supplied input, else
 * a value detected in update mode, else the field default) and normalizes the
 * supplied inputs through their transformers, then settles derived values,
 * conditional activation and fix-ups to a fixpoint, and finally validates the
 * active supplied inputs. Precedence per field is
 * input > detected > derived > default. It never knows what any field means:
 * all behaviour comes from the form declaration, with the reusable static
 * validate()/transform() of a consumer class (resolved by field id) as the
 * fallback.
 *
 * @package DrevOps\Tui\Engine
 */
class Engine {

  /**
   * The deriver for computed field values.
   */
  protected Deriver $deriver;

  /**
   * Construct an engine.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The form definition to run.
   * @param \DrevOps\Tui\Handler\HandlerRegistry $handlers
   *   The registry resolving a field id to its handler.
   */
  public function __construct(
    protected FormDefinition $form,
    protected HandlerRegistry $handlers,
  ) {
    $this->deriver = new Deriver();
  }

  /**
   * Collect the answers of the active fields.
   *
   * @param array<string,mixed> $inputs
   *   Pre-supplied values keyed by field id (from flags, env, prompts, ...).
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context (destination directory, update flag).
   *
   * @return \DrevOps\Tui\Answers\Answers
   *   The self-describing answer set with values and provenance.
   */
  public function collect(array $inputs, Context $context): Answers {
    $fields = $this->form->fields();

    // Headless collection has no panel to resolve option loaders lazily, so
    // resolve them up front - the values need their options to validate.
    $this->loadOptions($fields);

    [$values, $sources] = $this->resolveAll($fields, $inputs, $context);
    $values = $this->transformInputs($fields, $values, $sources);
    [$rules, $pinned] = $this->deriveRules($fields, $sources);
    [$active, $values] = $this->stabilize($fields, $values, $rules, $pinned);
    $this->guardInputs($fields, $values, $sources, $active);

    return Answers::forForm($this->form, $this->activeAnswers($fields, $values, $active), $this->provenanceFor($fields, $sources, $active));
  }

  /**
   * Resolve each field's option loader to its options, in place.
   *
   * A loader runs once; the resolved options replace it, so a later pass and
   * the interactive panel both see settled options.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields.
   */
  public function loadOptions(array $fields): void {
    foreach ($fields as $field) {
      if (!$field->optionsLoader instanceof \Closure) {
        continue;
      }

      $loaded = ($field->optionsLoader)();
      // A loader is documented to return a value => label map; coerce
      // defensively so a mistyped one degrades to no options, not an error.
      $field->options = Option::list(array_filter(is_array($loaded) ? $loaded : [], is_string(...)));
      $field->optionsLoader = NULL;
    }
  }

  /**
   * Resolve and settle every field's value, provenance and activation.
   *
   * The full-map twin of collect(): the same resolution and settling over the
   * whole form, but keeping every value-carrying field - an inactive one
   * retains its settled value and provenance, so a later activation change can
   * surface it without re-resolving - and skipping the input guard, which
   * belongs to the collection boundary. A display-only field (a note or a
   * progress row) carries no answer, so it appears only in the active map,
   * never the values.
   *
   * @param array<string,mixed> $inputs
   *   Pre-supplied values keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context.
   *
   * @return array{array<string,mixed>,array<string,\DrevOps\Tui\Answers\Provenance>,array<string,bool>}
   *   The settled values, the provenance and the active map, keyed by field id;
   *   the values and provenance cover every value-carrying field, active or
   *   not, while the active map also carries the display-only fields.
   */
  public function resolveState(array $inputs, Context $context): array {
    $fields = $this->form->fields();

    [$values, $sources] = $this->resolveAll($fields, $inputs, $context);
    $values = $this->transformInputs($fields, $values, $sources);
    [$rules, $pinned] = $this->deriveRules($fields, $sources);
    [$active, $values] = $this->stabilize($fields, $values, $rules, $pinned);

    $all = array_fill_keys(array_keys($sources), TRUE);

    return [$values, $this->provenanceFor($fields, $sources, $all), $active];
  }

  /**
   * Settle derived values, activation and fix-ups over an edited value set.
   *
   * The stabilization that runs at resolution time, re-runnable over values
   * that changed afterwards: derive rules recompute except where the pinned
   * map holds a field's value, conditions re-evaluate and fix-ups re-apply,
   * all to a fixpoint.
   *
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   * @param array<string,bool> $pinned
   *   Derive-ruled field ids that must not be recomputed.
   *
   * @return array{array<string,bool>,array<string,mixed>}
   *   The active map and the settled values.
   */
  public function settle(array $values, array $pinned): array {
    $fields = $this->form->fields();

    return $this->stabilize($fields, $values, $this->ruleMap($fields), $pinned);
  }

  /**
   * Resolve every field's initial value and its source, in field order.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,mixed> $inputs
   *   Pre-supplied values keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context.
   *
   * @return array{array<string,mixed>,array<string,\DrevOps\Tui\Engine\Source>}
   *   The resolved values and their sources, each keyed by field id.
   */
  protected function resolveAll(array $fields, array $inputs, Context $context): array {
    $values = [];
    $sources = [];

    foreach ($fields as $field) {
      // A display-only field carries no answer, so it never enters the value
      // and source maps: it is neither resolved nor allowed to influence a
      // later field's context.
      if ($field->type->isDisplayOnly()) {
        continue;
      }

      $resolved = new Context($context->directory, $values, $context->update, $context->version);
      [$value, $source] = $this->resolveInitial($field, $inputs, $resolved);
      $sources[$field->id] = $source;
      $values[$field->id] = $value;
    }

    return [$values, $sources];
  }

  /**
   * The derive rules and the pinned map of externally-supplied derive targets.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   The initial source per field id.
   *
   * @return array{array<string,\DrevOps\Tui\Derive\Derive>,array<string,bool>}
   *   The derive rules and the pinned map, each keyed by field id.
   */
  protected function deriveRules(array $fields, array $sources): array {
    $rules = $this->ruleMap($fields);

    $pinned = [];

    foreach (array_keys($rules) as $id) {
      $pinned[$id] = in_array($sources[$id], [Source::Input, Source::Detected], TRUE);
    }

    return [$rules, $pinned];
  }

  /**
   * The derive rules of the derive-ruled fields, keyed by field id.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   *
   * @return array<string,\DrevOps\Tui\Derive\Derive>
   *   The derive rules keyed by field id.
   */
  protected function ruleMap(array $fields): array {
    $rules = [];

    foreach ($fields as $field) {
      // A display-only field is absent from the value map, so it never
      // carries a derive rule that would resolve against a missing source.
      if ($field->type->isDisplayOnly()) {
        continue;
      }
      if ($field->derive !== NULL) {
        $rules[$field->id] = $field->derive;
      }
    }

    return $rules;
  }

  /**
   * Transform the supplied inputs so every later stage sees normalized values.
   *
   * Normalization happens before stabilization: conditions, derivations and
   * fix-ups must evaluate against the transformed value (e.g. a trimmed
   * string), not the raw input. Only supplied inputs transform: defaults and
   * derived values are the form's own, and discovered values were
   * validated (with a default fallback) at detection time.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,mixed> $values
   *   The resolved values keyed by field id.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   The initial source per field id.
   *
   * @return array<string,mixed>
   *   The values, with the supplied inputs transformed.
   */
  protected function transformInputs(array $fields, array $values, array $sources): array {
    foreach ($fields as $field) {
      if ($field->type->isDisplayOnly()) {
        continue;
      }
      if ($sources[$field->id] === Source::Input) {
        $values[$field->id] = $this->transformValue($field, $values[$field->id]);
      }
    }

    return $values;
  }

  /**
   * Validate the active supplied inputs, throwing on the first error.
   *
   * Only supplied inputs pass through the guard: defaults and derived values
   * are the form's own, and discovered values were validated (with a
   * default fallback) at detection time.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,mixed> $values
   *   The settled values keyed by field id.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   The initial source per field id.
   * @param array<string,bool> $active
   *   The active map.
   *
   * @throws \DrevOps\Tui\Engine\EngineException
   *   When a supplied input fails its type, bounds, validator or options.
   */
  protected function guardInputs(array $fields, array $values, array $sources, array $active): void {
    foreach ($fields as $field) {
      if ($field->type->isDisplayOnly()) {
        continue;
      }
      if (!($active[$field->id] ?? FALSE)) {
        continue;
      }
      if ($sources[$field->id] !== Source::Input) {
        continue;
      }

      $error = $this->validateValue($field, $values[$field->id]);
      if ($error !== NULL) {
        throw new EngineException(Translator::t('Invalid value for field "@id": @error', [
          '@id' => $field->id,
          '@error' => $error,
        ]));
      }
    }
  }

  /**
   * Resolve the initial value and its source for a field.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param array<string,mixed> $inputs
   *   Pre-supplied values keyed by field id.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context.
   *
   * @return array{mixed,\DrevOps\Tui\Engine\Source}
   *   The resolved value and its source.
   */
  protected function resolveInitial(Field $field, array $inputs, Context $context): array {
    if (array_key_exists($field->id, $inputs)) {
      return [$inputs[$field->id], Source::Input];
    }

    if ($context->update) {
      $detected = $this->discoverValue($field, $context);
      if ($detected !== NULL && $this->acceptsDetected($field, $detected)) {
        return [$detected, Source::Detected];
      }
    }

    if ($field->default instanceof \Closure) {
      return [($field->default)($context), Source::Default];
    }

    return [$field->default, Source::Default];
  }

  /**
   * Whether a discovered value is safe to adopt for a field.
   *
   * Discovered values come from arbitrary project files, not the declaration,
   * so one that fails the field's type, emptiness, bounds or options falls back
   * to the default instead of poisoning the answers.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $value
   *   The discovered value.
   *
   * @return bool
   *   TRUE when the value passes the field's shape and constraints.
   */
  protected function acceptsDetected(Field $field, mixed $value): bool {
    return $field->acceptsValue($value) && $field->requiredViolation($value) === NULL && $field->boundsViolation($value) === NULL && $field->pickerViolation($value) === NULL && $field->optionError($value) === NULL;
  }

  /**
   * Validate a supplied value: required, type, bounds, validator, then options.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $value
   *   The value to validate.
   *
   * @return string|null
   *   An error message, or NULL when the value is valid.
   */
  protected function validateValue(Field $field, mixed $value): ?string {
    // Emptiness is answered first, so a required field says so rather than
    // letting NULL read as a type error or an empty list as a count violation.
    $missing = $field->requiredViolation($value);
    if ($missing !== NULL) {
      return $missing;
    }

    if (!$field->acceptsValue($value)) {
      return Translator::t('must be @constraint.', ['@constraint' => $field->valueKind()]);
    }

    $violation = $field->boundsViolation($value);
    if ($violation !== NULL) {
      return Translator::t('must be @constraint.', ['@constraint' => $violation]);
    }

    $picker = $field->pickerViolation($value);
    if ($picker !== NULL) {
      return Translator::t('must be @constraint.', ['@constraint' => $picker]);
    }

    $validator = $field->validate ?? $this->handlers->validator($field->id);
    $error = $validator instanceof \Closure ? $validator($value) : NULL;
    if (is_string($error) && $error !== '') {
      return $error;
    }

    return $field->optionError($value);
  }

  /**
   * Transform a value: the declared transformer, else a reusable static one.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $value
   *   The accepted value.
   *
   * @return mixed
   *   The transformed value.
   */
  protected function transformValue(Field $field, mixed $value): mixed {
    $transformer = $field->transform ?? $this->handlers->transformer($field->id);

    return $transformer instanceof \Closure ? $transformer($value) : $value;
  }

  /**
   * Detect a value from the declared discovery rule.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context.
   *
   * @return mixed
   *   The detected value, or NULL.
   */
  protected function discoverValue(Field $field, Context $context): mixed {
    if ($field->discover instanceof DiscoverInterface) {
      return $field->discover->discover($context->directory);
    }

    if ($field->discover instanceof \Closure) {
      return ($field->discover)($context);
    }

    return NULL;
  }

  /**
   * Settle derived values, conditional activation and fix-ups to a fixpoint.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,mixed> $values
   *   The resolved values keyed by field id.
   * @param array<string,\DrevOps\Tui\Derive\Derive> $derive_rules
   *   Derive rules keyed by field id.
   * @param array<string,bool> $pinned
   *   Field ids that must not be re-derived (input or detected).
   *
   * @return array{array<string,bool>,array<string,mixed>}
   *   The active map and the settled values.
   */
  protected function stabilize(array $fields, array $values, array $derive_rules, array $pinned): array {
    $active = [];
    foreach ($fields as $field) {
      $active[$field->id] = TRUE;
    }

    // A settled state exits below, so the bound only guards a non-converging
    // cycle: field-count passes cover the longest possible chain, plus two for
    // the activation and fix-up interplay.
    $limit = count($fields) + 2;
    for ($i = 0; $i <= $limit; $i++) {
      $derived = $this->deriver->derive($derive_rules, $values, $pinned);

      $next_active = [];
      $answers = $this->activeAnswers($fields, $derived, $active);
      foreach ($fields as $field) {
        $next_active[$field->id] = $field->when === NULL || $field->when->matches($answers);
      }

      $next_values = $this->applyFixups($derived, $this->activeAnswers($fields, $derived, $next_active));

      if ($next_active === $active && $next_values === $values) {
        return [$active, $values];
      }

      $active = $next_active;
      $values = $next_values;
    }

    // @codeCoverageIgnoreStart
    return [$active, $values];
    // @codeCoverageIgnoreEnd
  }

  /**
   * Compute the provenance of every active field.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,\DrevOps\Tui\Engine\Source> $sources
   *   The initial source per field id.
   * @param array<string,bool> $active
   *   The active map.
   *
   * @return array<string,\DrevOps\Tui\Answers\Provenance>
   *   The provenance of each active field.
   */
  protected function provenanceFor(array $fields, array $sources, array $active): array {
    $provenance = [];
    foreach ($fields as $field) {
      if ($field->type->isDisplayOnly()) {
        continue;
      }
      if (!($active[$field->id] ?? FALSE)) {
        continue;
      }

      $source = $sources[$field->id];
      $provenance[$field->id] = match (TRUE) {
        $source === Source::Detected => Provenance::Detected,
        $field->derive !== NULL && $source === Source::Input => Provenance::Override,
        $field->derive !== NULL => Provenance::Derived,
        $source === Source::Input => Provenance::Edited,
        default => Provenance::Default,
      };
    }

    return $provenance;
  }

  /**
   * Restrict values to the active fields, in field order.
   *
   * @param \DrevOps\Tui\Model\Field[] $fields
   *   The fields, in order.
   * @param array<string,mixed> $values
   *   The resolved values.
   * @param array<string,bool> $active
   *   The active map.
   *
   * @return array<string,mixed>
   *   The answers of the active fields.
   */
  protected function activeAnswers(array $fields, array $values, array $active): array {
    $answers = [];
    foreach ($fields as $field) {
      if ($field->type->isDisplayOnly()) {
        continue;
      }

      if ($active[$field->id] ?? FALSE) {
        $answers[$field->id] = $values[$field->id] ?? NULL;
      }
    }

    return $answers;
  }

  /**
   * Apply the form's fix-up rules to the values.
   *
   * A fix-up sets its target field's value when its guard matches (or when it
   * has no guard): a literal `to`, or a copy of the `from` field's value.
   *
   * @param array<string,mixed> $values
   *   The current values.
   * @param array<string,mixed> $answers
   *   The active answers the guards evaluate against.
   *
   * @return array<string,mixed>
   *   The values after fix-ups.
   */
  protected function applyFixups(array $values, array $answers): array {
    foreach ($this->form->fixups as $fixup) {
      if ($fixup->when instanceof ConditionInterface && !$fixup->when->matches($answers)) {
        continue;
      }

      // A display-only field carries no value, so a fix-up can neither write
      // to one nor copy from one - reading a note's absent value would write
      // NULL over the target's settled value. A mistargeted rule is ignored.
      if ($this->form->field($fixup->set)?->type->isDisplayOnly()) {
        continue;
      }
      if ($this->form->field($fixup->from)?->type->isDisplayOnly()) {
        continue;
      }

      $values[$fixup->set] = $fixup->from !== '' ? ($values[$fixup->from] ?? NULL) : $fixup->to;
    }

    return $values;
  }

}
