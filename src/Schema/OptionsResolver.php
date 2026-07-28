<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\Field;
use DrevOps\Tui\Model\Option;

/**
 * Resolves a field's answer-driven options for machine-readable output.
 *
 * A field whose options follow the answers has none until something resolves
 * them, so a description of the form would advertise an empty list where a
 * real one exists. This settles them against a caller-provided context - the
 * answers known so far, none for a plain schema - so the description carries
 * what those answers actually allow. A resolver that cannot answer this
 * context leaves the list empty rather than failing the description, the way a
 * closure default that cannot resolve stands down to NULL.
 *
 * @package DrevOps\Tui\Schema
 */
final class OptionsResolver {

  /**
   * Resolve a field's options in place, if they follow the answers.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context the resolver is called with.
   */
  public static function resolve(Field $field, Context $context): void {
    if (!$field->optionsFor instanceof \Closure) {
      return;
    }

    try {
      $field->options = Option::resolved(($field->optionsFor)($context));
    }
    catch (\Throwable) {
      // Nothing this context can be told; the empty list stands.
    }
  }

}
