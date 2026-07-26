<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Handler\Context;
use DrevOps\Tui\Model\Field;

/**
 * Resolves a field's default for machine-readable output.
 *
 * A literal default is rendered as declared. For a closure default, a declared
 * representative default stands in for it; otherwise the closure is evaluated
 * against a caller-provided context carrying no prior answers, so a form whose
 * defaults are computed still advertises real values. A closure that cannot be
 * resolved without answers - it throws when evaluated - resolves to null.
 *
 * @package DrevOps\Tui\Schema
 */
final class DefaultResolver {

  /**
   * Resolve the default advertised for a field.
   *
   * @param \DrevOps\Tui\Model\Field $field
   *   The field.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context a closure default is evaluated against; its answers are the
   *   ones known so far - none, for a plain schema.
   *
   * @return mixed
   *   The literal default, the declared representative default, the closure's
   *   evaluated value, or NULL when a closure cannot be resolved.
   */
  public static function resolve(Field $field, Context $context): mixed {
    if (!$field->default instanceof \Closure) {
      return $field->default;
    }

    if ($field->hasSchemaDefault) {
      return $field->schemaDefault;
    }

    try {
      return ($field->default)($context);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
