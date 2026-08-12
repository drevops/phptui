<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Schema;

use DrevOps\PhpTui\Block\Field;
use DrevOps\PhpTui\Handler\Context;

/**
 * Resolves a field's default for machine-readable output.
 *
 * A literal default is rendered as declared. For a closure default, a declared
 * representative default stands in for it; otherwise the closure is evaluated
 * against a caller-provided context carrying no prior answers, so a form whose
 * defaults are computed still advertises real values. A closure that cannot be
 * resolved without answers - it throws when evaluated - resolves to null.
 *
 * @package DrevOps\PhpTui\Schema
 */
final class DefaultResolver {

  /**
   * Resolve the default advertised for a field.
   *
   * @param \DrevOps\PhpTui\Block\Field $field
   *   The field.
   * @param \DrevOps\PhpTui\Handler\Context $context
   *   The context a closure default is evaluated against; its answers are the
   *   ones known so far - none, for a plain schema.
   *
   * @return mixed
   *   The literal default, the declared representative default, the closure's
   *   evaluated value, or NULL when a closure cannot be resolved.
   */
  public static function resolve(Field $field, Context $context): mixed {
    $default = $field->value();

    if (!$default instanceof \Closure) {
      return $default;
    }

    if ($field->isSchemaDefault()) {
      return $field->schemaDefaultValue();
    }

    try {
      return $default($context);
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
