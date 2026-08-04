<?php

declare(strict_types=1);

namespace DrevOps\Tui\Model;

/**
 * Thrown when a form declaration is invalid.
 *
 * A declaration mistake is an argument a caller got wrong, so it lands in the
 * family a caller already catches for one: whichever surface refuses - a
 * builder, a block or a limit - one catch covers the lot.
 *
 * @package DrevOps\Tui\Model
 */
class FormException extends \InvalidArgumentException {

}
