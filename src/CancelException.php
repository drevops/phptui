<?php

declare(strict_types=1);

namespace DrevOps\PhpTui;

/**
 * Thrown when the user dismisses an interactive session via the cancel button.
 *
 * A cancel ends the session without a submit, like the Ctrl-C abort, so the
 * answers edited before it are not a completed form. It extends
 * {@see InterruptException} so one catch covers both aborts; a caller that
 * treats an explicit cancel differently catches this class first.
 *
 * @package DrevOps\PhpTui
 */
class CancelException extends InterruptException {

}
