<?php

declare(strict_types=1);

namespace DrevOps\Tui\Schema;

use DrevOps\Tui\Block\Field;
use DrevOps\Tui\Handler\Context;

/**
 * Resolves a field's answer-driven options for machine-readable output.
 *
 * A field whose options follow the answers has none until something resolves
 * them, so a description of the form would advertise an empty list where a
 * real one exists. This settles them against a caller-provided context - the
 * answers known so far, none for a plain schema - so the description carries
 * what those answers actually allow. A resolver that cannot answer this
 * context empties the list rather than failing the description, the way a
 * closure default that cannot resolve stands down to NULL.
 *
 * @package DrevOps\Tui\Schema
 */
final class OptionsResolver {

  /**
   * Resolve a field's options in place, if they follow the answers.
   *
   * @param \DrevOps\Tui\Block\Field $field
   *   The field.
   * @param \DrevOps\Tui\Handler\Context $context
   *   The context the resolver is called with.
   */
  public static function resolve(Field $field, Context $context): void {
    $resolver = $field->resolver();

    if (!$resolver instanceof \Closure) {
      return;
    }

    try {
      $field->settle($resolver($context));
    }
    catch (\Throwable) {
      // A field's options are settled state that outlives one call, so a set
      // resolved for some earlier context is still sitting there. Nothing can
      // be said about this one, and saying the last one instead would be a
      // description of the wrong form.
      $field->settle([]);
    }
  }

}
