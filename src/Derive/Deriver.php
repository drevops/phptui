<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Derive;

use DrevOps\PhpTui\Terminal\Ansi;

/**
 * Recomputes derived field values until chains settle to a fixpoint.
 *
 * Each rule is a {@see Derive} owning its own computation; fields the user
 * has pinned (overridden) are left untouched.
 *
 * @package DrevOps\PhpTui\Derive
 */
class Deriver {

  /**
   * Recompute derived values until they stop changing.
   *
   * @param array<string,\DrevOps\PhpTui\Derive\Derive> $rules
   *   Derive rules keyed by field id.
   * @param array<string,mixed> $values
   *   The current values keyed by field id.
   * @param array<string,bool> $overridden
   *   Field ids the user has pinned; these are not recomputed.
   *
   * @return array<string,mixed>
   *   The values with derived fields recomputed.
   */
  public function derive(array $rules, array $values, array $overridden): array {
    // A derive chain advances one link per pass, so rule-count passes settle
    // the longest chain; the extra pass verifies nothing changed before exit.
    $limit = count($rules) + 1;

    for ($i = 0; $i <= $limit; $i++) {
      $changed = FALSE;

      foreach ($rules as $id => $rule) {
        if ($overridden[$id] ?? FALSE) {
          continue;
        }

        // A derive template is consumer text and is never validated.
        $computed = Ansi::sanitizeValue($rule->compute($values));
        if (($values[$id] ?? NULL) !== $computed) {
          $values[$id] = $computed;
          $changed = TRUE;
        }
      }

      if (!$changed) {
        break;
      }
    }

    return $values;
  }

}
