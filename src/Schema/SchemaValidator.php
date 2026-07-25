<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Model\FormDefinition;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Translation\Translator;

/**
 * Validates an answer set against the configuration.
 *
 * Checks value types, option membership and required questions, and skips
 * questions whose `when` condition is not met by the answer set. Returns a
 * list of actionable error messages (empty when the set is valid).
 *
 * @package DrevOps\Tui\Schema
 */
class SchemaValidator {

  /**
   * Construct a validator.
   *
   * @param \DrevOps\Tui\Model\FormDefinition $form
   *   The configuration to validate against.
   */
  public function __construct(protected FormDefinition $form) {
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
    $known = [];

    foreach ($this->form->fields() as $field) {
      $known[$field->id] = TRUE;

      // A display-only field (a note or a progress row) carries no answer, so
      // it is never required and any value supplied for it is ignored.
      if ($field->type->isDisplayOnly()) {
        continue;
      }

      if ($field->when !== NULL && !$field->when->matches($answers)) {
        continue;
      }

      if (!array_key_exists($field->id, $answers)) {
        if ($field->required) {
          $errors[] = Translator::t('Missing required question "@id".', ['@id' => $field->id]);
        }

        continue;
      }

      $error = $this->validateValue($field, $answers[$field->id]);
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
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   The first error, or NULL when valid.
   */
  protected function validateValue(Field $field, mixed $value): ?string {
    if (!$field->acceptsValue($value)) {
      return $this->constraintMessage($field, $field->valueKind());
    }

    if ($field->required && $this->isEmpty($value)) {
      return Translator::t('Question "@id" is required.', ['@id' => $field->id]);
    }

    $bounds_error = $this->checkBounds($field, $value);
    if ($bounds_error !== NULL) {
      return $bounds_error;
    }

    return $this->checkOptions($field, $value);
  }

  /**
   * Check a value against the field's declared number or date bounds.
   *
   * @param \DrevOps\Tui\Model\Field $field
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
   * Frame a constraint fragment as a question-scoped error message.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param string $constraint
   *   The constraint fragment (e.g. "a string", "between 1 and 10").
   *
   * @return string
   *   The framed message.
   */
  protected function constraintMessage(Field $field, string $constraint): string {
    return Translator::t('Question "@id" must be @constraint.', ['@id' => $field->id, '@constraint' => $constraint]);
  }

  /**
   * Whether a value is empty.
   *
   * @param mixed $value
   *   The value.
   *
   * @return bool
   *   TRUE when empty.
   */
  protected function isEmpty(mixed $value): bool {
    return in_array($value, ['', [], NULL], TRUE);
  }

  /**
   * Check option membership for choice fields.
   *
   * Rejects any supplied value that is not a selectable option, telling a
   * disabled option apart from an unknown one.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param mixed $value
   *   The value.
   *
   * @return string|null
   *   An error, or NULL when valid.
   */
  protected function checkOptions(Field $field, mixed $value): ?string {
    $error = $field->optionError($value);

    return $error === NULL ? NULL : Translator::t('Question "@id": @error.', ['@id' => $field->id, '@error' => $error]);
  }

}
