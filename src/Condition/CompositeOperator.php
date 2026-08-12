<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Condition;

/**
 * How a composite condition combines its parts.
 *
 * @package DrevOps\PhpTui\Condition
 */
enum CompositeOperator: string {

  // Matches when every combined condition matches.
  case All = 'all';

  // Matches when at least one combined condition matches.
  case Any = 'any';

  // Matches when the single combined condition does not match.
  case Not = 'not';

}
