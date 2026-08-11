<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Block\Panel;
use DrevOps\Tui\Block\Tree;
use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Translation\Translator;

/**
 * Validates an answer set against the configuration.
 *
 * Checks value types, option membership and required questions, and skips
 * questions the answer set leaves off the form - the ones whose own `when`
 * condition it does not meet, and the ones inside a section it takes away. A
 * question whose options follow the answers is checked against the set those
 * very answers resolve to. Returns a list of actionable error messages (empty
 * when the set is valid).
 *
 * This is where a payload is finally judged complete, because only here is
 * there an answer set to measure the rules against: a question is owed when the
 * answers put it on the form and it is required, and owed nothing otherwise.
 * The published schemas describe those rules but cannot resolve them, so what
 * they say about requiredness is written to never refuse a set this accepts.
 *
 * @package DrevOps\Tui\Schema
 */
class SchemaValidator {

  /**
   * Construct a validator.
   *
   * @param \DrevOps\Tui\Block\Panel $root
   *   The declared tree to validate against, read from the panel every declared
   *   panel hangs from.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The run context an options resolver is evaluated against, its answers
   *   replaced by the set under validation; defaults to an empty context.
   */
  public function __construct(protected Panel $root, protected Context $context = new Context()) {
  }

  /**
   * Validate an answer set.
   *
   * @param array<string,mixed> $answers
   *   The answers keyed by question id.
   *
   * @return list<string>
   *   The validation errors; empty when valid.
   */
  public function validate(array $answers): array {
    $errors = [];

    // Every row the tree holds, including the ones that only show: an id that
    // shows is still an id the form knows, so a stray value for one is ignored
    // rather than reported as a question nobody asked.
    $known = array_fill_keys(Tree::ids($this->root), TRUE);
    // Settled rather than read once: a value inside a section the payload takes
    // away is a value the form never asked for, and collection drops it before
    // the conditions are measured again. Reading it once would owe a question
    // that a run of the same payload never asks.
    $within = Tree::settled($this->root, $answers);
    $fields = Tree::fields($this->root);
    // What the form actually asks with: a value inside a section the payload
    // took away - or one keyed by a block that collects nothing - must not feed
    // another field's option list, or membership here would allow what
    // collection refuses. Collection only ever answers with fields, so the
    // resolver context is narrowed to the same set.
    $ids = array_fill_keys(array_map(static fn(Field $field): string => $field->id(), $fields), TRUE);
    $asked = array_intersect_key(Tree::held($this->root, $answers, $within), $ids);

    foreach ($fields as $field) {
      if (!($within[spl_object_id($field)] ?? TRUE)) {
        continue;
      }

      if (!array_key_exists($field->id(), $answers)) {
        if ($field->isRequired()) {
          $errors[] = Translator::t('Missing required question "@id".', ['@id' => $field->id()]);
        }

        continue;
      }

      // Options that follow the answers describe what this very answer set
      // allows, so they are settled against it - carried on the run context, so
      // a resolver reading the directory or the update flag sees what it would
      // see during collection - before membership is checked.
      OptionsResolver::resolve($field, new Context($this->context->directory, $asked, $this->context->update, $this->context->version));

      $error = $this->validateValue($field, $answers[$field->id()]);
      if ($error !== NULL) {
        $errors[] = $error;
      }
    }

    foreach (array_keys($answers) as $id) {
      if (!isset($known[(string) $id])) {
        $errors[] = Translator::t('Unknown question "@id".', ['@id' => (string) $id]);
      }
    }

    return $errors;
  }

  /**
   * Validate a single value against its field.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   The first error, or NULL when valid.
   */
  protected function validateValue(Field $field, mixed $value): ?string {
    // Emptiness is answered first, so a required field says so rather than
    // letting NULL read as a type error or an empty list as a count violation.
    $missing = $field->requiredViolation($value);
    if ($missing !== NULL) {
      return Translator::t('Question "@id": @error', ['@id' => $field->id(), '@error' => $missing]);
    }

    if (!$field->acceptsValue($value)) {
      return $this->constraintMessage($field, $field->valueKind());
    }

    $bounds_error = $this->checkBounds($field, $value);
    if ($bounds_error !== NULL) {
      return $bounds_error;
    }

    $picker_error = $this->checkPicker($field, $value);
    if ($picker_error !== NULL) {
      return $picker_error;
    }

    return $this->checkOptions($field, $value);
  }

  /**
   * Check a value against the field's declared number or date bounds.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when in range (or when the field declares no bounds).
   */
  protected function checkBounds(Field $field, mixed $value): ?string {
    $violation = $field->boundsViolation($value);

    return $violation === NULL ? NULL : $this->constraintMessage($field, $violation);
  }

  /**
   * Check a value against the field's declared file picker constraints.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when within limits (or the field declares no picker
   *   constraints).
   */
  protected function checkPicker(Field $field, mixed $value): ?string {
    $violation = $field->pickerViolation($value);

    return $violation === NULL ? NULL : $this->constraintMessage($field, $violation);
  }

  /**
   * Frame a constraint fragment as a question-scoped error message.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param string $constraint
   *   The constraint fragment (e.g. "a string", "between 1 and 10").
   *
   * @return string
   *   The framed message.
   */
  protected function constraintMessage(Field $field, string $constraint): string {
    return Translator::t('Question "@id" must be @constraint.', ['@id' => $field->id(), '@constraint' => $constraint]);
  }

  /**
   * Check option membership for choice fields.
   *
   * Rejects any supplied value that is not a selectable option, telling a
   * disabled option apart from an unknown one.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when valid.
   */
  protected function checkOptions(Field $field, mixed $value): ?string {
    $error = $field->entryViolation($value);

    return $error === NULL ? NULL : Translator::t('Question "@id": @error.', ['@id' => $field->id(), '@error' => $error]);
  }

}
