<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Builder;

use DrevOps\PhpTui\Condition\ConditionInterface;
use DrevOps\PhpTui\Terminal\Ansi;

/**
 * A post-settle fix-up: set a field's value when a condition holds.
 *
 * Declared with named arguments - `new Fixup(set: 'cdn', to: FALSE, when: new
 * Condition('environment', ne: 'prod'))`. The new value is the `to` literal,
 * or a copy of another field's value when `from` names one. With no condition
 * the fix-up always applies.
 *
 * @package DrevOps\PhpTui\Builder
 */
final readonly class Fixup {

  /**
   * The literal value to set, ignored when another field is copied from.
   */
  public mixed $to;

  /**
   * Construct a fix-up.
   *
   * @param string $set
   *   The id of the field to set.
   * @param mixed $to
   *   The literal value to set (ignored when $from names a field).
   * @param string $from
   *   The id of a field whose value is copied (empty to use $to).
   * @param \DrevOps\PhpTui\Condition\ConditionInterface|null $when
   *   The guard condition (NULL to always apply).
   */
  public function __construct(
    public string $set,
    mixed $to = NULL,
    public string $from = '',
    public ?ConditionInterface $when = NULL,
  ) {
    $this->to = Ansi::sanitizeValue($to);
  }

}
