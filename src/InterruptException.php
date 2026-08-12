<?php

declare(strict_types=1);

namespace DrevOps\PhpTui;

/**
 * Thrown when the user aborts an interactive session with the interrupt key.
 *
 * An interrupt (Ctrl-C) is an abort, not a submit: the facade raises this so a
 * caller's result-handling path is skipped rather than fed the partial answers
 * collected before the abort. Catch it to exit quietly (a conventional SIGINT
 * exit code is 130). Catching it also covers the {@see CancelException}
 * subclass, so one catch handles every user abort.
 *
 * @package DrevOps\PhpTui
 */
class InterruptException extends \RuntimeException {

}
