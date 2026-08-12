<?php

declare(strict_types=1);

namespace DrevOps\PhpTui;

/**
 * Thrown when a form declaration is invalid.
 *
 * A declaration mistake is an argument the caller got wrong, so the class
 * extends the exception family a caller already catches for one. Whichever
 * surface throws it - a builder, a block or a limit - one catch covers all.
 *
 * @package DrevOps\PhpTui
 */
class FormException extends \InvalidArgumentException {

}
