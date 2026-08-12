<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Testing;

use DrevOps\PhpTui\Field\FieldInterface;
use DrevOps\PhpTui\Input\Key;

/**
 * Drives a field to completion from a key stream.
 *
 * @package DrevOps\PhpTui\Testing
 */
final class FieldRunner {

  /**
   * Feed keys to a field until it completes or is cancelled.
   *
   * @param \DrevOps\PhpTui\Field\FieldInterface $field
   *   The field to drive.
   * @param \DrevOps\PhpTui\Testing\KeyStreamInterface $keys
   *   The key source.
   *
   * @return mixed
   *   The accepted value, or NULL when cancelled.
   */
  public static function run(FieldInterface $field, KeyStreamInterface $keys): mixed {
    while (($key = $keys->read()) instanceof Key) {
      $field->handle($key);

      if ($field->isComplete() || $field->isCancelled()) {
        break;
      }
    }

    return $field->isCancelled() ? NULL : $field->value();
  }

}
