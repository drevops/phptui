<?php

declare(strict_types=1);

namespace DrevOps\Tui;

/**
 * Thrown when a collection cannot take the answers it was given.
 *
 * With no screen there is nobody to retype a value the form refuses, and no row
 * to say so on, so the whole collection fails rather than handing back answers
 * one of which was never accepted. It sits beside {@see InterruptException} and
 * {@see CancelException} because all three are ways a collection ends without a
 * complete set of answers, so one import covers every ending.
 *
 * @package DrevOps\Tui
 */
class CollectException extends \RuntimeException {

}
